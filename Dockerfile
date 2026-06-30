FROM php:8.4-cli-alpine

# Instalar dependencias del sistema y herramientas de ayuda para extensiones PHP
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache git bash openssh-client wget \
    && install-php-extensions pdo_pgsql intl zip opcache apcu gd iconv

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalar Symfony CLI
RUN wget https://get.symfony.com/cli/installer -O - | bash \
    && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

# Configurar directorio de trabajo
WORKDIR /var/www

# Exponer el puerto para el servidor de desarrollo de Symfony
EXPOSE 8000

# Comando por defecto para iniciar el servidor de Symfony escuchando en todas las interfaces
CMD ["symfony", "server:start", "--no-tls", "--port=8000", "--allow-all-ip"]
