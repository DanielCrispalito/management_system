FROM php:8.2-apache

# FIX konflik MPM
RUN a2dismod mpm_event \
 && a2enmod mpm_prefork

COPY . /var/www/html/

RUN docker-php-ext-install mysqli pdo pdo_mysql

EXPOSE 80