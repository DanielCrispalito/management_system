FROM php:8.2-apache

# FIX konflik MPM (Pastikan hanya satu yang aktif)
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
 && a2enmod mpm_prefork

COPY . /var/www/html/

RUN docker-php-ext-install mysqli pdo pdo_mysql

EXPOSE 80