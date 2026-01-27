# ProjetClinique - PHP + MySQL sur Render
# Image nginx + PHP-FPM
FROM richarvey/nginx-php-fpm:3.1.6

# Copier tout le projet dans la racine web
COPY . /var/www/html/

# Racine du site (pas de dossier public comme Laravel)
ENV WEBROOT=/var/www/html
ENV SKIP_COMPOSER=1
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1

# Droits pour uploads et sessions
RUN chown -R www-data:www-data /var/www/html/uploads 2>/dev/null || true

CMD ["/start.sh"]
