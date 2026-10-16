#!/bin/bash
set -e

# Crear directorio de tokens si no existe con permisos amplios
mkdir -p /tmp/webtty_tokens
chmod 0777 /tmp/webtty_tokens

# Iniciar webtty daemon en background si el binario existe
if [ -x /var/www/html/bin/webtty ]; then
    echo "[Entrypoint] Iniciando daemon webtty..."
    /var/www/html/bin/webtty -host 127.0.0.1 -port 7681 -tokens-dir /tmp/webtty_tokens &
fi

# Ejecutar el comando por defecto (Apache)
exec apache2-foreground "$@"
