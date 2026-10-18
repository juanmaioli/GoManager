# ⚡ GoManager SvrInfo

> Panel web accesible de monitoreo, control, métricas de servidores y terminal SSH interactiva embebida con `gosvrinfo`, WebTTY y SQLite.

---

## 📋 Descripción

**GoManager SvrInfo** es una solución integral y ligera desarrollada en **PHP 8.3**, **Go** y **SQLite** sobre **Docker**, orientada al monitoreo centralizado y administración de infraestructura. Se conecta de forma remota y segura mediante SSH a cada equipo para ejecutar `gosvrinfo`, recolectando en tiempo real métricas de hardware, rendimiento y conectividad, e incorpora una **terminal SSH interactiva directa en el navegador** impulsada por un daemon WebSocket en Go y Xterm.js.

Diseñado con un enfoque prioritario en la **accesibilidad** y **alta legibilidad** (Bootstrap 5.3, iconografía vectorial FontAwesome 7 `fa-light` y `fa-brands`), adaptabilidad dinámica de temas y visualización compacta de métricas críticas.

---

## ✨ Características Principales

- 🎛️ **Filtrado Interactivo en Tiempo Real:** Las tarjetas de métricas globales del dashboard ("Total Equipos", "Online", "Con Alertas / Offline") funcionan como filtros dinámicos con un clic o teclado (`Enter`/`Espacio`), con resaltado visual del estado activo, contador de equipos visibles y botón de restablecimiento rápido.
- 📋 **Historial Autónomo de Incidentes y Fidelidad de Reportes:** Registro automático en SQLite de caídas de conexión (`desconectado_en`), resolución al volver online (`reconectado_en`), cómputo de duración para auditoría y preservación estricta de la fecha del último reporte válido (`ultimo_check`) sin sobreescrituras espurias durante desconexiones.
- 💻 **Terminal SSH Embebida (WebTTY):** Consola PTY interactiva completa en el navegador vía WebSockets (`wss://`), renderizada con tipografía **CaskaydiaCove Nerd Font Mono**, modo **Pantalla Completa** (botón y atajo `Alt+Enter`), controles interactivos de **tamaño de texto** (botones de zoom, indicador en px, persistencia en `localStorage` y atajos `Ctrl++`/`Ctrl+-`/`Ctrl+0`), colores ANSI, atajos de teclado (`Ctrl+C`, `Ctrl+D`), soporte para editores (`nano`, `vim`, `htop`), ajuste dinámico de tamaño y tokens de sesión de un solo uso.
- 🚀 **Instalador Remoto de Clientes vía SCP:** Botón de acceso directo en la barra superior de cada servidor y en la configuración para transferir automáticamente el ejecutable `gosvrinfo` a `/tmp/gosvrinfo` en el servidor destino, con detección dinámica de binarios disponibles en `./clientes` (x86_64, arm64, etc.) y modal explicativo con el comando exacto para moverlo a `/usr/local/bin` con un solo clic.
- 🌓 **Selector de Tema Dinámico (Claro / Oscuro):** Conmutación instantánea mediante el atributo nativo `data-bs-theme`, persistencia local en `localStorage` y transiciones fluidas de color sin parpadeo (FOUC).
- 🐧 **Identificación Visual de Distribuciones:** Detección automática del sistema operativo mostrando el logo oficial de la distribución Linux (Debian, Ubuntu, Fedora, CentOS, Arch, openSUSE, Raspberry Pi, etc.).
- 🧭 **Menú Lateral Responsivo (Sidebar):** Barra de navegación fija izquierda, compacta y accesible, con transición fluida a menú offcanvas en dispositivos móviles y selector de tema integrado.
- 🗂️ **Dashboard en 4 Columnas:** Cuadrícula de alta densidad responsive con tarjetas ordenadas alfabéticamente (A-Z) para localización inmediata de servidores.
- 🎯 **Tarjetas Ultra-Compactas:** Diseño limpio con foco en CPU, RAM, IP pública y accesos directos por iconos con botones sólidos sin saturación de texto.
- 📦 **Recursos 100% Locales (Offline):** Dependencias completas en el proyecto (Bootstrap 5.3, Xterm.js, extensiones de color, FontAwesome 7 Pro y fuentes vectoriales WOFF2) sin llamadas a CDNs externos.
- 🔑 **Autenticación Dual SSH:** Soporte para clave privada (RSA, Ed25519, PEM) o usuario y contraseña (`sshpass`).
- 🔍 **Detalle Técnico Exhaustivo:** Fila principal de 5 tarjetas clave (CPU, RAM, Sistema, Conectividad, GPU), cuadrícula 50%/50% (Discos y Red vs Incidentes), barra de acciones rápidas (`Ssh`, `Recargar`, `Configurar`, `Json` en `btn-night` e `Instalar gosvrinfo`), modal flotante para JSON en bruto y edición directa con prueba de conexión SSH.
- ⚙️ **Administración (ABM):** Gestión ágil de servidores con ordenamiento alfabético (A-Z) por nombre, herramienta incorporada para **probar conexión SSH**, instalar el cliente remoto y abrir terminal directa antes de guardar cambios.
- ⏱️ **Auto-chequeo Periódico desde la Web:** Selector configurable en la barra lateral (Desactivado, 1, 5, 10 y 15 min) con cuenta regresiva en vivo, persistencia en `localStorage`, pausa automática inteligente si la terminal SSH está abierta y botón de ejecución manual directa.
- 🔄 **Sincronización Flexible:** Actualización en lote por AJAX (`api/sync_all.php`), individual bajo demanda y modo CLI (`sync_cli.php`) para tareas programadas (cron).
- 🕒 **Normalización de Hora Local:** Conversión transparente de marcas temporales UTC de SQLite a hora oficial argentina (`America/Argentina/Buenos_Aires`) en dashboard, detalles y configuración.
- 🗄️ **Base de Datos Embebida:** SQLite con esquema relacional auto-inicializable (`servidores`, `metricas`, `incidentes`) sin mantenimiento de servicios externos.

---

## 🛠️ Tecnologías Utilizadas

| Componente | Tecnología |
| :--- | :--- |
| **Backend Web** | PHP 8.3 (Apache, PDO SQLite, GD, OpenSSL, OpenSSH, SSHPass) |
| **Terminal Daemon** | Go 1.26 (Gorilla WebSocket, Golang Crypto SSH, PTY) |
| **Terminal Frontend** | Xterm.js 5.3 + Fit Addon 0.8 (100% local) |
| **Base de Datos** | SQLite 3 |
| **Frontend UI** | Bootstrap 5.3 (Doble Tema Claro/Oscuro, Sidebar Offcanvas) + FontAwesome 7 Pro (`fa-light`, `fa-brands`) |
| **Infraestructura** | Docker & Docker Compose v2 |
| **Cliente de Métricas** | Binario `gosvrinfo` (Go) |

---

## 🚀 Despliegue con Docker

### 1. Clonar o ingresar al proyecto
```bash
cd /home/juan/VirtualMachines/Docker/GoManager
```

### 2. Levantar el entorno
```bash
docker compose up -d --build
```

### 3. Acceder al panel web
Abrí el navegador e ingresá en:
- **Dashboard:** `https://localhost:8090/index.php`
- **Configuración de Equipos:** `https://localhost:8090/config.php`

*(Aceptá la excepción de seguridad por el certificado SSL autofirmado de desarrollo).*

---

## 📁 Estructura del Proyecto

```text
├── Dockerfile                  # Imagen PHP 8.3 con Apache, OpenSSH, proxy WebSocket y WebTTY
├── compose.yml                 # Definición del contenedor y volúmenes persistentes
├── entrypoint.sh               # Script supervisor que mantiene WebTTY 24/7 y Apache
├── README.md                   # Documentación oficial del proyecto
├── services/                   # Microservicios auxiliares
│   └── webtty/                 # Daemon en Go para puente WebSocket <-> SSH PTY
├── data_apache/                # Configuración de Apache, proxy_wstunnel y certificados SSL
├── data_php/                   # Configuración personalizada de PHP (php.ini)
└── data_www/                   # Código fuente de la aplicación web
    ├── index.php               # Dashboard general de métricas en 4 columnas
    ├── config.php              # Administración (ABM), test SSH e instalación de clientes
    ├── servidor.php            # Vista detallada de métricas por equipo y terminal SSH
    ├── sync_cli.php            # Sincronización por línea de comandos / cron
    ├── api/                    # Endpoints para peticiones AJAX
    │   ├── sync.php            # Sincronización en caliente de un servidor
    │   ├── sync_all.php        # Sincronización masiva de servidores activos
    │   ├── test_ssh.php        # Validación inmediata de credenciales SSH
    │   ├── install_client.php  # Transferencia remota vía SCP de binarios gosvrinfo
    │   └── terminal_session.php# Emisión de tokens de un solo uso para WebTTY
    ├── bin/                    # Binarios ejecutables auxiliares (webtty compilado)
    ├── css/                    # Hojas de estilo locales
    │   ├── styles.css          # Variables de tema claro/oscuro y componentes
    │   ├── bootstrap.min.css   # Framework CSS Bootstrap 5.3
    │   ├── bootstrap-color-extension.css
    │   ├── all.css             # FontAwesome 7 Pro
    │   └── xterm.css           # Estilos para consola interactiva Xterm.js
    ├── js/                     # Scripts clientes locales
    │   ├── app.js              # Manejo de tema claro/oscuro y persistencia
    │   ├── bootstrap.bundle.min.js
    │   ├── xterm.js            # Motor de emulación de terminal
    │   ├── xterm-addon-fit.js  # Addon de auto-ajuste de dimensiones
    │   └── terminal.js         # Controlador cliente de la sesión SSH embebida
    ├── webfonts/               # Fuentes FontAwesome y CaskaydiaCove Nerd Font Mono (TTF / WOFF2)
    ├── includes/               # Componentes compartidos del backend
    │   ├── db.php              # Conexión SQLite y esquema de tablas
    │   ├── ssh.php             # Conector SSH para ejecución remota y copia SCP
    │   ├── helpers.php         # Mapeo de logos de distro y funciones auxiliares
    │   └── navbar.php          # Menú lateral accesible con switch de tema
    ├── db/                     # Almacenamiento protegido de database.sqlite
    └── clientes/               # Binarios compilados de gosvrinfo (amd64 / arm64)
```

---

## ⏰ Sincronización Automática (Cron)

Podés programar la actualización periódica ejecutando el script CLI dentro del contenedor:

```bash
docker exec -it goManager_web php /var/www/html/sync_cli.php
```

---

## 👤 Autor

- **Juan Gabriel Maioli**
