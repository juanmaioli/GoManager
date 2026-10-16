/**
 * terminal.js - Emulador de Terminal SSH Web con xterm.js y WebSocket
 */

let currentTerm = null;
let currentFitAddon = null;
let currentWs = null;
let currentServerId = null;
let currentServerName = '';

function inicializarModalTerminal() {
    if (document.getElementById('modalTerminalSSH')) return;

    const modalHtml = `
    <div class="modal fade" id="modalTerminalSSH" tabindex="-1" aria-labelledby="modalTerminalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 95vw;">
            <div class="modal-content border-secondary shadow-lg" style="background-color: #0d1117; color: #c9d1d9;">
                <div class="modal-header border-secondary py-2 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-light fa-terminal text-info fs-5"></i>
                        <h5 class="modal-title fw-bold mb-0 text-light" id="modalTerminalLabel">Terminal SSH</h5>
                        <span class="badge bg-secondary font-monospace" id="termServerBadge">Servidor</span>
                        <span class="badge bg-warning text-dark font-monospace" id="termStatusBadge">Iniciando...</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-info text-dark fw-bold" onclick="reconnectTerminal()" title="Reconectar">
                            <i class="fa-light fa-arrows-rotate me-1"></i>Reconectar
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary fw-bold" onclick="limpiarTerminalPantalla()" title="Limpiar">
                            <i class="fa-light fa-eraser me-1"></i>Limpiar
                        </button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar terminal" onclick="cerrarTerminalSSH()"></button>
                    </div>
                </div>
                <div class="modal-body p-2" style="background-color: #0d1117;">
                    <div id="terminal-box" style="width: 100%; height: 65vh; min-height: 400px; max-height: 80vh;"></div>
                </div>
                <div class="modal-footer border-secondary py-1 px-3 justify-content-between text-secondary" style="font-size: 0.8rem;">
                    <div>
                        <span class="me-3"><i class="fa-light fa-keyboard me-1"></i>Soporta copiar / pegar, atajos estándar y Ctrl+C</span>
                        <span id="termDimensions">80x24</span>
                    </div>
                    <div>
                        <span>GoManager WebTTY</span>
                    </div>
                </div>
            </div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Redimensionar terminal al abrir el modal completamente
    const modalEl = document.getElementById('modalTerminalSSH');
    modalEl.addEventListener('shown.bs.modal', () => {
        if (currentFitAddon && currentTerm) {
            setTimeout(() => {
                currentFitAddon.fit();
                currentTerm.focus();
                actualizarDimensionesInfo();
            }, 100);
        }
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        cerrarTerminalSSH();
    });

    window.addEventListener('resize', () => {
        if (currentFitAddon && currentTerm && modalEl.classList.contains('show')) {
            currentFitAddon.fit();
            actualizarDimensionesInfo();
        }
    });
}

async function abrirTerminalSSH(serverId, serverNombre) {
    currentServerId = serverId;
    currentServerName = serverNombre || `Servidor #${serverId}`;

    inicializarModalTerminal();

    const modalEl = document.getElementById('modalTerminalSSH');
    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    
    document.getElementById('modalTerminalLabel').textContent = `Terminal: ${currentServerName}`;
    document.getElementById('termServerBadge').textContent = currentServerName;
    setTermStatus('Conectando...', 'warning');

    bsModal.show();
    conectarSSH();
}

async function conectarSSH() {
    cerrarTerminalSSH(false);

    const container = document.getElementById('terminal-box');
    container.innerHTML = '';

    // Crear instancia xterm.js
    currentTerm = new Terminal({
        cursorBlink: true,
        cursorStyle: 'block',
        fontSize: 14,
        fontFamily: 'Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
        theme: {
            background: '#0d1117',
            foreground: '#c9d1d9',
            cursor: '#58a6ff',
            cursorAccent: '#0d1117',
            selectionBackground: '#1f6feb',
            black: '#484f58',
            red: '#ff7b72',
            green: '#3fb950',
            yellow: '#d29922',
            blue: '#58a6ff',
            magenta: '#bc8cff',
            cyan: '#39c5cf',
            white: '#b1bac4',
            brightBlack: '#6e7681',
            brightRed: '#ffa198',
            brightGreen: '#56d364',
            brightYellow: '#e3b341',
            brightBlue: '#79c0ff',
            brightMagenta: '#d2a8ff',
            brightCyan: '#56d4dd',
            brightWhite: '#f0f6fc'
        }
    });

    // Cargar addon fit
    if (typeof FitAddon !== 'undefined' && FitAddon.FitAddon) {
        currentFitAddon = new FitAddon.FitAddon();
        currentTerm.loadAddon(currentFitAddon);
    }

    currentTerm.open(container);

    if (currentFitAddon) {
        currentFitAddon.fit();
        actualizarDimensionesInfo();
    }

    currentTerm.write(`\x1b[36m[GoManager WebTTY]\x1b[0m Solicitando sesión segura para ${currentServerName}...\r\n`);

    try {
        const resp = await fetch(`api/terminal_session.php?id=${currentServerId}`);
        const data = await resp.json();

        if (!data.success || !data.token) {
            setTermStatus('Error de Sesión', 'danger');
            currentTerm.write(`\r\n\x1b[31m[Error]\x1b[0m ${data.error || 'No se pudo generar token de conexión.'}\r\n`);
            return;
        }

        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const cols = currentTerm.cols || 80;
        const rows = currentTerm.rows || 24;
        const wsUrl = `${protocol}//${window.location.host}/ws?token=${encodeURIComponent(data.token)}&cols=${cols}&rows=${rows}`;

        currentWs = new WebSocket(wsUrl);

        currentWs.onopen = () => {
            setTermStatus('Conectado', 'success');
            currentTerm.focus();
        };

        currentWs.onmessage = (evt) => {
            currentTerm.write(evt.data);
        };

        currentWs.onerror = () => {
            setTermStatus('Error de Conexión', 'danger');
        };

        currentWs.onclose = () => {
            setTermStatus('Desconectado', 'secondary');
            currentTerm.write('\r\n\x1b[33m[Sesión SSH finalizada]\x1b[0m\r\n');
        };

        // Enviar teclas presionadas
        currentTerm.onData((key) => {
            if (currentWs && currentWs.readyState === WebSocket.OPEN) {
                currentWs.send(key);
            }
        });

        // Enviar evento de redimensionamiento
        currentTerm.onResize((size) => {
            if (currentWs && currentWs.readyState === WebSocket.OPEN) {
                currentWs.send(JSON.stringify({
                    type: 'resize',
                    cols: size.cols,
                    rows: size.rows
                }));
            }
            actualizarDimensionesInfo();
        });

    } catch (e) {
        setTermStatus('Error', 'danger');
        currentTerm.write(`\r\n\x1b[31m[Error de Red]\x1b[0m ${e.message}\r\n`);
    }
}

function setTermStatus(text, bgClass) {
    const badge = document.getElementById('termStatusBadge');
    if (badge) {
        badge.className = `badge bg-${bgClass} ${bgClass === 'warning' || bgClass === 'info' ? 'text-dark' : 'text-light'} font-monospace`;
        badge.textContent = text;
    }
}

function actualizarDimensionesInfo() {
    const dim = document.getElementById('termDimensions');
    if (dim && currentTerm) {
        dim.textContent = `${currentTerm.cols}x${currentTerm.rows}`;
    }
}

function reconnectTerminal() {
    if (currentServerId) {
        conectarSSH();
    }
}

function limpiarTerminalPantalla() {
    if (currentTerm) {
        currentTerm.clear();
        currentTerm.focus();
    }
}

function cerrarTerminalSSH(resetIds = true) {
    if (currentWs) {
        try {
            currentWs.close();
        } catch (_) {}
        currentWs = null;
    }
    if (currentTerm) {
        try {
            currentTerm.dispose();
        } catch (_) {}
        currentTerm = null;
    }
    currentFitAddon = null;
    if (resetIds) {
        currentServerId = null;
        currentServerName = '';
    }
}
