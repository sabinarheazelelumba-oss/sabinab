ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-apache

# PDO MySQL (Aiven MySQL is reached over TLS; mysqlnd + OpenSSL are built in)
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Silence the "Could not reliably determine the server's name" warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy app files (see .dockerignore: .env is NOT copied, use Render environment variables)
COPY . /var/www/html/

# Point Apache document root to public/
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot ${APACHE_DOCUMENT_ROOT}|g' /etc/apache2/sites-available/000-default.conf \
&& sed -i 's|<Directory /var/www/html>|<Directory ${APACHE_DOCUMENT_ROOT}>|g' /etc/apache2/apache2.conf

# Fix permissions
RUN chown -R www-data:www-data /var/www/html \
&& chmod -R 755 /var/www/html \
&& chmod +x /var/www/html/docker/entrypoint.sh

# Render sets $PORT (default 10000); the entrypoint makes Apache listen on it
EXPOSE 10000

CMD ["/var/www/html/docker/entrypoint.sh"]
