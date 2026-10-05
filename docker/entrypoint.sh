#!/bin/sh
set -e

# Render tells the container which port to listen on through $PORT (default 10000).
PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Cache / rate-limit files are written here
mkdir -p /var/www/html/runtime/cache
chown -R www-data:www-data /var/www/html/runtime

exec apache2-foreground
