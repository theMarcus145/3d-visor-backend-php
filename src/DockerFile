FROM php:8.2-apache

# Copiar archivos
COPY . /var/www/html/

# Habilitar mod_rewrite para Apache
RUN a2enmod rewrite

# Configuración del documento raíz
RUN sed -i 's|/var/www/html|/var/www/html/public|' /etc/apache2/sites-available/000-default.conf

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalar dependencias de PHP si las hay
WORKDIR /var/www/html
RUN composer install

#s
EXPOSE 80
