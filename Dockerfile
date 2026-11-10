FROM php:8.2-apache

# Install SSL certificates and MySQL extension
RUN apt-get update && apt-get install -y ca-certificates && update-ca-certificates
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy project files into web root
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
