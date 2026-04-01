FROM php:8.2-apache

COPY . /var/www/html/

RUN docker-php-ext-install mysqli pdo pdo_mysql

EXPOSE 80

# Jalankan fix MPM pada saat Container *Start* (bukan di build time) 
# menggunakan perintah resmi apache (a2dismod) agar menghapus file .load beserta .conf nya secara bersih.
CMD ["/bin/sh", "-c", "a2dismod mpm_event mpm_worker; a2enmod mpm_prefork; apache2-foreground"]