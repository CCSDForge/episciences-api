#!/bin/bash
set -e

# Ajouter l'inclusion du vhost uniquement si elle n'existe pas déjà
if ! grep -q "Include conf/extra/episciences-api.conf" /usr/local/apache2/conf/httpd.conf; then
  echo "Include conf/extra/episciences-api.conf" >> /usr/local/apache2/conf/httpd.conf
fi

# (Optionnel) Fixer les permissions si nécessaire
# chown -R www-data:www-data /var/www/data /var/www/cache /var/www/logs

# Démarrer Apache en mode foreground
exec "$@"
