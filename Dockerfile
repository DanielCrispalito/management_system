FROM php:8.2-apache

COPY . /var/www/html/

RUN docker-php-ext-install mysqli pdo pdo_mysql

EXPOSE 80

# Jalankan fix MPM pada saat Container *Start* (bukan di build time) 
# karena Railway sering me-load konfigurasi bawaan mereka saat container booting.
CMD ["/bin/sh", "-c", "rm -f /etc/apache2/mods-enabled/mpm_*.load && a2enmod mpm_prefork && apache2-foreground"]