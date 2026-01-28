# ProjetClinique - PHP + MySQL sur Render
# Image nginx + PHP-FPM
FROM richarvey/nginx-php-fpm:3.1.6

# Copier tout le projet dans la racine web
COPY . /var/www/html/

# Racine du site (pas de dossier public comme Laravel)
ENV WEBROOT=/var/www/html
ENV SKIP_COMPOSER=1
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=0
ENV REAL_IP_HEADER=1

# Corriger 502 : PHP-FPM en TCP (9000) au lieu du socket manquant sur Render
RUN sed -i 's|^listen = .*|listen = 127.0.0.1:9000|' /usr/local/etc/php-fpm.d/www.conf

# Droits pour uploads et sessions
RUN chown -R www-data:www-data /var/www/html/uploads 2>/dev/null || true

CMD ["/start.sh"]
