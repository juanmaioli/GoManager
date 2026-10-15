# ⚡ GoManager SvrInfo

> Panel web accesible de monitoreo, control y métricas de servidores, VPS y contenedores vía SSH con `gosvrinfo` y SQLite.

---

## 📋 Descripción

**GoManager SvrInfo** es una solución integral y ligera desarrollada en **PHP 8.3** y **SQLite** sobre **Docker**, orientada al monitoreo centralizado de infraestructura. Se conecta de forma remota y segura mediante SSH a cada equipo para ejecutar `gosvrinfo`, recolectando en tiempo real datos de hardware, rendimiento y conectividad.

Diseñado con un enfoque prioritario en la **accesibilidad** y **alta legibilidad** (Bootstrap 5.3 en Modo Oscuro, iconografía vectorial FontAwesome `fa-light`), ideal para una rápida interpretación visual de métricas críticas.

---

## ✨ Características Principales

- 🧭 **Menú Lateral Responsivo (Sidebar):** Navegación fija en el lateral izquierdo, compacta y accesible, con transición a menú desplegable tipo *offcanvas* en dispositivos móviles y tablets.
- 🎨 **Iconografía FontAwesome `fa-light`:** Interfaz libre de emojis, estandarizada con iconos vectoriales limpios y accesibles.
- 📦 **Recursos 100% Locales (Offline):** Dependencias de frontend (Bootstrap 5.3, extensiones de color, FontAwesome 7 y webfonts) alojadas localmente sin conexión a CDN externos.
- 🖥️ **Dashboard en Tiempo Real:** Tarjetas individuales por servidor con estado online/error, barras de uso de CPU, RAM, espacio libre en disco y Uptime formateado.
- 🔑 **Autenticación Dual SSH:** Soporte para autenticación mediante **clave privada** (RSA, Ed25519, PEM) o **usuario y contraseña** (`sshpass`).
- 🔍 **Detalle Técnico Exhaustivo:** Vista profunda de cada equipo con métricas de procesador (cores, velocidad), memoria, tarjetas gráficas (GPU/VRAM), tablas de almacenamiento particionado e interfaces de red activas con sus direcciones IP y MAC.
- ⚙️ **Administración (ABM):** Gestión ágil de servidores con herramienta incorporada para **probar la conexión SSH** antes de guardar.
- 📄 **Visor JSON Integrado:** Inspección directa del payload completo emitido por `gosvrinfo`.
- 🔄 **Sincronización Flexible:** Actualización bajo demanda por AJAX (servidor individual o global) y modo CLI (`sync_cli.php`) para tareas programadas (cron).
- 🗄️ **Base de Datos Embebida:** SQLite con esquema relacional auto-inicializable y sin dependencias de motores pesados externos.

---

## 🛠️ Tecnologías Utilizadas

| Componente | Tecnología |
| :--- | :--- |
| **Backend** | PHP 8.3 (Apache, PDO SQLite, GD, OpenSSL, OpenSSH, SSHPass) |
| **Base de Datos** | SQLite 3 |
| **Frontend** | Bootstrap 5.3 (Modo Oscuro, Sidebar Offcanvas) + FontAwesome 7 Pro (`fa-light`) |
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
    ├── index.php               # Dashboard general de métricas
    ├── config.php              # Administración (ABM) de servidores y test SSH
    ├── servidor.php            # Vista detallada de métricas por equipo
    ├── sync_cli.php            # Sincronización por línea de comandos / cron
    ├── api/                    # Endpoints para peticiones AJAX
    │   ├── sync.php            # Sincronización en caliente de métricas
    │   └── test_ssh.php        # Validación inmediata de credenciales SSH
    ├── css/                    # Hojas de estilo locales (Bootstrap, Colores, FontAwesome)
    ├── js/                     # Librerías JavaScript locales (Bootstrap Bundle)
    ├── webfonts/               # Fuentes vectoriales FontAwesome (WOFF2)
    ├── includes/               # Componentes compartidos del backend
    │   ├── db.php              # Conexión SQLite y esquema de tablas
    │   ├── ssh.php             # Conector SSH para ejecución remota
    │   ├── helpers.php         # Funciones auxiliares y formateadores
    │   └── navbar.php          # Menú lateral accesible (Sidebar con offcanvas móvil)
    ├── db/                     # Almacenamiento protegido de database.sqlite
    └── clientes/               # Binarios compilados de gosvrinfo (amd64 / arm64)
```

---

## ⏰ Sincronización Automática (Cron)

Podés programar la actualización automática ejecutando el script CLI dentro del contenedor:

```bash
docker exec -it goManager_web php /var/www/html/sync_cli.php
```

---

## 👤 Autor

- **Juan Gabriel Maioli**
