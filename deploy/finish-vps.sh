#!/usr/bin/env bash
set -euo pipefail
if [ "$(id -u)" -ne 0 ]; then echo 'Exécuter en root.' >&2; exit 1; fi
cd /var/www/inventory-management
if [ ! -f .env ] || [ ! -f vendor/autoload.php ]; then
    echo 'Installation initiale incomplète : .env ou dépendances absents.' >&2; exit 1
fi
php8.2 artisan config:cache
php8.2 artisan view:clear
if [ ! -e public/storage ]; then php8.2 artisan storage:link; fi
chown -R root:www-data /var/www/inventory-management
chmod -R u=rwX,g=rX,o= /var/www/inventory-management
chmod -R g+rwX storage bootstrap/cache public/uploads
chmod 640 .env
cp deploy/nginx-port5000.conf /etc/nginx/sites-available/inventory-management
if [ ! -e /etc/nginx/sites-enabled/inventory-management ]; then
    ln -s /etc/nginx/sites-available/inventory-management /etc/nginx/sites-enabled/inventory-management
fi
nginx -t
systemctl enable --now php8.2-fpm
systemctl reload nginx
if command -v ufw >/dev/null && ufw status | grep -q '^Status: active'; then ufw allow 5000/tcp; fi
curl --fail --silent --output /dev/null "http://127.0.0.1:5000/login"
printf '\nApplication : http://%s:5000/login\n' "${INVENTORY_IP:-213.136.81.100}"
printf 'Identifiants : /root/inventory-admin.txt\n'
