package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/gorilla/websocket"
	"golang.org/x/crypto/ssh"
)

type ServerToken struct {
	ServerID     int    `json:"server_id"`
	ServerNombre string `json:"server_nombre"`
	Host         string `json:"host"`
	Puerto       int    `json:"puerto"`
	Usuario      string `json:"usuario"`
	AuthTipo     string `json:"auth_tipo"`
	Password     string `json:"password"`
	ClavePrivada string `json:"clave_privada"`
	CreatedAt    int64  `json:"created_at"`
}

type ResizeMessage struct {
	Type string `json:"type"`
	Cols int    `json:"cols"`
	Rows int    `json:"rows"`
}

var (
	tokensDir = flag.String("tokens-dir", "/tmp/webtty_tokens", "Directorio donde se almacenan los tokens temporales")
	port      = flag.Int("port", 7681, "Puerto para escuchar conexiones HTTP/WebSocket")
	host      = flag.String("host", "127.0.0.1", "Host para escuchar")
	upgrader  = websocket.Upgrader{
		CheckOrigin: func(r *http.Request) bool {
			return true // Permitir orígenes internos y proxys
		},
		ReadBufferSize:  1024 * 4,
		WriteBufferSize: 1024 * 4,
	}
)

func main() {
	flag.Parse()

	if err := os.MkdirAll(*tokensDir, 0700); err != nil {
		log.Fatalf("Error al crear directorio de tokens %s: %v", *tokensDir, err)
	}

	http.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.Write([]byte(`{"status":"ok","service":"webtty"}`))
	})

	http.HandleFunc("/ws", handleWebSocket)

	addr := fmt.Sprintf("%s:%d", *host, *port)
	log.Printf("[WebTTY] Servidor iniciado en http://%s (tokens en %s)", addr, *tokensDir)
	if err := http.ListenAndServe(addr, nil); err != nil {
		log.Fatalf("[WebTTY] Error fatal en ListenAndServe: %v", err)
	}
}

func handleWebSocket(w http.ResponseWriter, r *http.Request) {
	token := strings.TrimSpace(r.URL.Query().Get("token"))
	if token == "" || len(token) > 64 {
		http.Error(w, "Token no especificado o inválido", http.StatusBadRequest)
		return
	}

	// Sanitizar token para evitar path traversal
	cleanToken := filepath.Base(token)
	tokenPath := filepath.Join(*tokensDir, cleanToken+".json")

	data, err := os.ReadFile(tokenPath)
	if err != nil {
		http.Error(w, "Token no encontrado o expirado", http.StatusForbidden)
		return
	}

	// Eliminar el token inmediatamente (uso único)
	_ = os.Remove(tokenPath)

	var srv ServerToken
	if err := json.Unmarshal(data, &srv); err != nil {
		http.Error(w, "Payload de token inválido", http.StatusInternalServerError)
		return
	}

	// Validar expiración (máximo 60 segundos desde creación)
	if time.Now().Unix()-srv.CreatedAt > 60 {
		http.Error(w, "Token expirado", http.StatusForbidden)
		return
	}

	// Obtener dimensiones iniciales de terminal
	cols := 80
	rows := 24
	if cStr := r.URL.Query().Get("cols"); cStr != "" {
		if c, err := strconv.Atoi(cStr); err == nil && c > 10 && c < 500 {
			cols = c
		}
	}
	if rStr := r.URL.Query().Get("rows"); rStr != "" {
		if row, err := strconv.Atoi(rStr); err == nil && row > 3 && row < 300 {
			rows = row
		}
	}

	// Mejorar la conexión a WebSocket
	ws, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Printf("[WebTTY] Error en WebSocket Upgrade: %v", err)
		return
	}
	defer ws.Close()

	sendWSMessage(ws, fmt.Sprintf("\r\n\x1b[36m[GoManager]\x1b[0m Conectando a \x1b[1;32m%s\x1b[0m (%s@%s:%d)...\r\n", srv.ServerNombre, srv.Usuario, srv.Host, srv.Puerto))

	// Configurar autenticación SSH
	var authMethods []ssh.AuthMethod
	if srv.AuthTipo == "key" && strings.TrimSpace(srv.ClavePrivada) != "" {
		signer, err := ssh.ParsePrivateKey([]byte(srv.ClavePrivada))
		if err != nil {
			sendWSMessage(ws, fmt.Sprintf("\r\n\x1b[31m[Error Clave Privada]\x1b[0m %v\r\n", err))
			return
		}
		authMethods = append(authMethods, ssh.PublicKeys(signer))
	} else {
		authMethods = append(authMethods, ssh.Password(srv.Password))
	}

	sshConfig := &ssh.ClientConfig{
		User:            srv.Usuario,
		Auth:            authMethods,
		HostKeyCallback: ssh.InsecureIgnoreHostKey(),
		Timeout:         10 * time.Second,
	}

	sshAddr := fmt.Sprintf("%s:%d", srv.Host, srv.Puerto)
	sshClient, err := ssh.Dial("tcp", sshAddr, sshConfig)
	if err != nil {
		sendWSMessage(ws, fmt.Sprintf("\r\n\x1b[31m[Error SSH]\x1b[0m No se pudo conectar a %s: %v\r\n", sshAddr, err))
		return
	}
	defer sshClient.Close()

	session, err := sshClient.NewSession()
	if err != nil {
		sendWSMessage(ws, fmt.Sprintf("\r\n\x1b[31m[Error Sesión]\x1b[0m %v\r\n", err))
		return
	}
	defer session.Close()

	// Configurar PTY
	modes := ssh.TerminalModes{
		ssh.ECHO:          1,
		ssh.TTY_OP_ISPEED: 14400,
		ssh.TTY_OP_OSPEED: 14400,
	}
	if err := session.RequestPty("xterm-256color", rows, cols, modes); err != nil {
		sendWSMessage(ws, fmt.Sprintf("\r\n\x1b[31m[Error PTY]\x1b[0m %v\r\n", err))
		return
	}

	stdinPipe, err := session.StdinPipe()
	if err != nil {
		sendWSMessage(ws, fmt.Sprintf("\r\n\x1b[31m[Error Stdin]\x1b[0m %v\r\n", err))
		return
	}
	defer stdinPipe.Close()

	stdoutPipe, err := session.StdoutPipe()
	if err != nil {
		sendWSMessage(ws, fmt.Sprintf("\r\n\x1b[31m[Error Stdout]\x1b[0m %v\r\n", err))
		return
	}

	stderrPipe, err := session.StderrPipe()
	if err != nil {
		sendWSMessage(ws, fmt.Sprintf("\r\n\x1b[31m[Error Stderr]\x1b[0m %v\r\n", err))
		return
	}

	if err := session.Shell(); err != nil {
		sendWSMessage(ws, fmt.Sprintf("\r\n\x1b[31m[Error Shell]\x1b[0m %v\r\n", err))
		return
	}

	sendWSMessage(ws, "\x1b[32m[Conexión establecida con éxito]\x1b[0m\r\n\r\n")

	var once sync.Once
	closeAll := func() {
		once.Do(func() {
			_ = session.Close()
			_ = sshClient.Close()
			_ = ws.Close()
		})
	}

	// Goroutine: SSH -> WebSocket
	go func() {
		defer closeAll()
		buf := make([]byte, 4096)
		reader := io.MultiReader(stdoutPipe, stderrPipe)
		for {
			n, err := reader.Read(buf)
			if n > 0 {
				if wErr := ws.WriteMessage(websocket.TextMessage, buf[:n]); wErr != nil {
					return
				}
			}
			if err != nil {
				return
			}
		}
	}()

	// Bucle principal: WebSocket -> SSH
	for {
		msgType, p, err := ws.ReadMessage()
		if err != nil {
			break
		}

		if msgType == websocket.TextMessage || msgType == websocket.BinaryMessage {
			// Verificar si es un mensaje de redimensionamiento JSON
			if len(p) > 0 && p[0] == '{' {
				var resize ResizeMessage
				if err := json.Unmarshal(p, &resize); err == nil && resize.Type == "resize" && resize.Cols > 0 && resize.Rows > 0 {
					_ = session.WindowChange(resize.Rows, resize.Cols)
					continue
				}
			}

			// Entrada normal del teclado
			if _, wErr := stdinPipe.Write(p); wErr != nil {
				break
			}
		}
	}

	closeAll()
}

func sendWSMessage(ws *websocket.Conn, msg string) {
	_ = ws.WriteMessage(websocket.TextMessage, []byte(msg))
}
