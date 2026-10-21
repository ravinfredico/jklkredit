#!/bin/sh
set -e

# Hosts like Railway and Render pass the port to listen on in $PORT.
PORT="${PORT:-80}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p storage/uploads
# First start with SQLite: create the database and demo data. Skipped once it exists.
if [ -z "$DB_DSN" ] && [ ! -f storage/database.sqlite ]; then
  php bin/setup.php
fi
chown -R www-data:www-data storage

exec apache2-foreground
