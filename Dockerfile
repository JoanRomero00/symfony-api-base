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

# Copiar y configurar el script de entrada (Entrypoint)
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh && sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh

# Exponer el puerto para el servidor de desarrollo de Symfony
EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]

# Comando por defecto para iniciar el servidor de Symfony escuchando en todas las interfaces
CMD ["symfony", "server:start", "--no-tls", "--port=8000", "--allow-all-ip"]
