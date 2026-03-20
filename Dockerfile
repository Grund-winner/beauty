FROM php:8.2-apache

# Installer extensions utiles
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Activer mod_rewrite
RUN a2enmod rewrite

# Copier fichiers
COPY . /var/www/html/

# Permissions upload
RUN chmod -R 777 /var/www/html/uploads

EXPOSE 80