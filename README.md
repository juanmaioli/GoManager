# ⚡ GoManager SvrInfo

> Panel web accesible de monitoreo, control y métricas de servidores, VPS y contenedores vía SSH con `gosvrinfo` y SQLite.

---

## 📋 Descripción

**GoManager SvrInfo** es una solución integral y ligera desarrollada en **PHP 8.3** y **SQLite** sobre **Docker**, orientada al monitoreo centralizado de infraestructura. Se conecta de forma remota y segura mediante SSH a cada equipo para ejecutar `gosvrinfo`, recolectando en tiempo real datos de hardware, rendimiento y conectividad.

Diseñado con un enfoque prioritario en la **accesibilidad** y **alta legibilidad** (Bootstrap 5.3, iconografía vectorial FontAwesome 7 `fa-light` y `fa-brands`), adaptabilidad dinámica de temas y visualización compacta de métricas críticas.

---

## ✨ Características Principales

- 🌓 **Selector de Tema Dinámico (Claro / Oscuro):** Conmutación instantánea mediante el atributo nativo `data-bs-theme`, persistencia local en `localStorage` y transiciones fluidas de color sin parpadeo (FOUC).
- 🐧 **Identificación Visual de Distribuciones:** Detección automática del sistema operativo mostrando el logo oficial de la distribución Linux (Debian, Ubuntu, Fedora, CentOS, Arch, openSUSE, Raspberry Pi, etc.).
- 🧭 **Menú Lateral Responsivo (Sidebar):** Barra de navegación fija izquierda, compacta y accesible, con transición fluida a menú offcanvas en dispositivos móviles y selector de tema integrado.
- 🗂️ **Dashboard en 4 Columnas:** Cuadrícula de alta densidad responsive con tarjetas ordenadas alfabéticamente (A-Z) para localización inmediata de servidores.
- 🎯 **Tarjetas Ultra-Compactas:** Diseño limpio con foco en CPU, RAM, IP pública y accesos directos por iconos sin saturación de texto.
- 📦 **Recursos 100% Locales (Offline):** Dependencias completas en el proyecto (Bootstrap 5.3, extensiones de color, FontAwesome 7 Pro y fuentes vectoriales WOFF2) sin llamadas a CDNs externos.
- 🔑 **Autenticación Dual SSH:** Soporte para clave privada (RSA, Ed25519, PEM) o usuario y contraseña (`sshpass`).
- 🔍 **Detalle Técnico Exhaustivo:** Vista profunda de cada equipo con métricas de CPU, memoria, tarjetas gráficas (GPU/VRAM), almacenamiento particionado e interfaces de red.
- ⚙️ **Administración (ABM):** Gestión ágil de servidores con herramienta incorporada para **probar conexión SSH** antes de guardar cambios.
- 🔄 **Sincronización Flexible:** Actualización bajo demanda por AJAX (individual o masiva) y modo CLI (`sync_cli.php`) para tareas programadas (cron).
- 🗄️ **Base de Datos Embebida:** SQLite con esquema relacional auto-inicializable y sin mantenimiento de servicios de base de datos externos.

---

## 🛠️ Tecnologías Utilizadas

| Componente | Tecnología |
| :--- | :--- |
| **Backend** | PHP 8.3 (Apache, PDO SQLite, GD, OpenSSL, OpenSSH, SSHPass) |
| **Base de Datos** | SQLite 3 |
| **Frontend** | Bootstrap 5.3 (Doble Tema Claro/Oscuro, Sidebar Offcanvas) + FontAwesome 7 Pro (`fa-light`, `fa-brands`) |
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
├── Dockerfile                  # Imagen PHP 8.3 con Apache, OpenSSH y SSHPass
├── compose.yml                 # Definición del contenedor y volúmenes persistentes
├── README.md                   # Documentación oficial del proyecto
├── data_apache/                # Configuración de Apache y certificados SSL
├── data_php/                   # Configuración personalizada de PHP (php.ini)
└── data_www/                   # Código fuente de la aplicación web
    ├── index.php               # Dashboard general de métricas en 4 columnas
    ├── config.php              # Administración (ABM) de servidores y test SSH
    ├── servidor.php            # Vista detallada de métricas por equipo
    ├── sync_cli.php            # Sincronización por línea de comandos / cron
    ├── api/                    # Endpoints para peticiones AJAX
    │   ├── sync.php            # Sincronización en caliente de métricas
    │   └── test_ssh.php        # Validación inmediata de credenciales SSH
    ├── css/                    # Hojas de estilo locales
    │   ├── styles.css          # Variables de tema claro/oscuro y componentes
    │   ├── bootstrap.min.css   # Framework CSS Bootstrap 5.3
    │   ├── bootstrap-color-extension.css
    │   └── all.css             # FontAwesome 7 Pro
    ├── js/                     # Scripts clientes locales
    │   ├── app.js              # Manejo de tema claro/oscuro y persistencia
    │   └── bootstrap.bundle.min.js
    ├── webfonts/               # Fuentes vectoriales FontAwesome (WOFF2)
    ├── includes/               # Componentes compartidos del backend
    │   ├── db.php              # Conexión SQLite y esquema de tablas
    │   ├── ssh.php             # Conector SSH para ejecución remota
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
