# Dockerfile para PHP 8.3 con Apache y extensiones necesarias
FROM php:8.3-apache

# Instalamos dependencias del sistema y extensiones de PHP
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    openssl \
    openssh-client \
    sshpass \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli \
    && docker-php-ext-enable gd mysqli

# Habilitamos módulos de Apache
RUN a2enmod rewrite ssl socache_shmcb proxy proxy_wstunnel proxy_http

# Generamos certificados SSL autofirmados
RUN mkdir -p /etc/apache2/ssl \
    && openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/apache2/ssl/apache.key \
    -out /etc/apache2/ssl/apache.crt \
    -subj "/C=AR/ST=Buenos Aires/L=CABA/O=Drawers/OU=IT/CN=drawers.com.ar"

# Copiamos script de arranque
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Ajustamos permisos básicos
RUN chown -R www-data:www-data /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
