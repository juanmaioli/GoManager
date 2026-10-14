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
RUN a2enmod rewrite ssl socache_shmcb

# Generamos certificados SSL autofirmados
RUN mkdir -p /etc/apache2/ssl \
    && openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/apache2/ssl/apache.key \
    -out /etc/apache2/ssl/apache.crt \
    -subj "/C=AR/ST=Buenos Aires/L=CABA/O=Drawers/OU=IT/CN=drawers.com.ar"

# Creamos una configuración de VirtualHost para SSL si no existe o usamos la por defecto
# Nota: Como montamos ./apache_data:/etc/apache2 en compose.yml, 
# los cambios manuales en el contenedor pueden ser sobrescritos por el volumen.
# Es mejor asegurarse de que la configuración viva en el volumen.

# Ajustamos permisos básicos
RUN chown -R www-data:www-data /var/www/html
