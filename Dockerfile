FROM php:8.2-apache

# Installation des extensions nécessaires
RUN docker-php-ext-install pdo pdo_mysql

# Activation de mod_rewrite pour Apache (si .htaccess est utilisé)
RUN a2enmod rewrite

# Copie des fichiers sources
COPY . /var/www/html/

# Ajustement des permissions
RUN chown -R www-data:www-data /var/www/html
