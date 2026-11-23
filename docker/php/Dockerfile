# docker/php/Dockerfile
FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git curl openssl \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql zip

# Módulos de Apache: rutas de Laravel, HTTPS y cabeceras
RUN a2enmod rewrite ssl headers

# Ocultar versión de Apache/PHP
RUN echo "ServerTokens Prod\nServerSignature Off" > /etc/apache2/conf-available/hardening.conf && a2enconf hardening \
    && echo "expose_php = Off" > /usr/local/etc/php/conf.d/hardening.ini

# Certificado AUTOFIRMADO solo para laboratorio (localhost). En producción usa un certificado real.
RUN mkdir -p /etc/ssl/haki && openssl req -x509 -nodes -newkey rsa:2048 -days 365 \
    -keyout /etc/ssl/haki/localhost.key -out /etc/ssl/haki/localhost.crt \
    -subj "/CN=localhost" -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"

# Sitios: el puerto 80 solo redirige a HTTPS; el 443 sirve la aplicación desde /public
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/default-ssl.conf /etc/apache2/sites-available/default-ssl.conf
RUN a2ensite default-ssl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN chown -R www-data:www-data /var/www/html

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs
