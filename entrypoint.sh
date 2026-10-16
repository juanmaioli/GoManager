#!/bin/bash
set -e

# Crear directorio de tokens si no existe con permisos amplios
mkdir -p /tmp/webtty_tokens
chmod 0777 /tmp/webtty_tokens

# Supervisor en background para mantener WebTTY activo 24/7
(
    while true; do
        if [ -x /var/www/html/bin/webtty ]; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] [Entrypoint] Iniciando daemon webtty..." >> /var/log/webtty.log
            /var/www/html/bin/webtty -host 127.0.0.1 -port 7681 -tokens-dir /tmp/webtty_tokens >> /var/log/webtty.log 2>&1 || true
        fi
        sleep 2
    done
) &

# Ejecutar Apache
if [ "$1" = 'apache2-foreground' ] || [ -z "$1" ]; then
    exec apache2-foreground
fi

exec "$@"
