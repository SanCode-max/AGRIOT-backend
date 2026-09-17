
FROM php:8.2-fpm

# Instala dependencias del sistema y extensiones necesarias para Laravel y MySQL
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Obtiene Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copia los archivos del proyecto al contenedor
WORKDIR /var/www
COPY . /var/www

# Instala las dependencias de Composer para producción
RUN composer install --no-dev --optimize-autoloader

# Otorga permisos a las carpetas de almacenamiento
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Puerto interno
EXPOSE 8000

# Comando de inicio: Ejecuta migraciones y levanta el servidor
CMD php artisan config:clear && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000