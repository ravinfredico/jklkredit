FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpng-dev \
 && docker-php-ext-install gd pdo_mysql \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && printf '<Directory /var/www/html/public>\n  AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/app.conf \
 && a2enconf app

WORKDIR /var/www/html
COPY . .
RUN chmod +x docker-entrypoint.sh

# storage/ holds the SQLite database and uploads; mount a volume here to keep them.
VOLUME ["/var/www/html/storage"]
CMD ["./docker-entrypoint.sh"]
