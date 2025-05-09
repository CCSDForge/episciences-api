#!/bin/bash
set -e

uid=$(stat -c %u /var/www/htdocs)
gid=$(stat -c %g /var/www/htdocs)

if [ "$(id -u)" -eq 0 ] && [ "$(id -g)" -eq 0 ]; then
  if [ $# -eq 0 ]; then
    php-fpm --allow-to-run-as-root
  else
    exec "$@"
  fi
fi

sed -i -E "s/www-data:x:[0-9]+:[0-9]+:/www-data:x:$uid:$gid:/g" /etc/passwd
sed -i -E "s/www-data:x:[0-9]+:/www-data:x:$gid:/g" /etc/group

# Création des répertoires nécessaires s'ils n'existent pas
mkdir -p /var/www/data /var/www/cache /var/www/logs
mkdir -p /var/www/htdocs/var/cache /var/www/htdocs/var/log

# Attribution des permissions larges (lecture/écriture/exécution pour tous)
chmod -R 777 /var/www/htdocs/var/cache /var/www/htdocs/var/log

# Attribution des droits à www-data
chown -R www-data:www-data /var/www/data /var/www/cache /var/www/logs
chown -R www-data:www-data /var/www/htdocs/var/cache /var/www/htdocs/var/log


# Installation des dépendances via Composer (en tant que www-data)
su www-data -s /bin/sh -c "cd /var/www/htdocs && composer install --no-interaction --prefer-dist --optimize-autoloader"
# Vider le cache Symfony si le script console existe
if [ -f "/var/www/htdocs/bin/console" ]; then
  su www-data -s /bin/sh -c "cd /var/www/htdocs && php bin/console cache:clear --env=dev"
fi

user=$(id -un)
if [ $# -eq 0 ]; then
  php-fpm
else
  gosu "$user" "$@"
fi