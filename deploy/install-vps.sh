#!/usr/bin/env bash
set -euo pipefail
if [ "$(id -u)" -ne 0 ]; then echo 'Exécuter en root.' >&2; exit 1; fi
if [ -z "${INVENTORY_IP:-}" ] || ! [[ "$INVENTORY_IP" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo 'Définir INVENTORY_IP avec l’adresse IPv4 publique du VPS.' >&2; exit 1
fi
if ss -ltnH 'sport = :5000' | grep -q .; then echo 'Le port 5000 est déjà occupé.' >&2; exit 1; fi
if [ -e /var/www/inventory-management ] || [ -e /etc/nginx/sites-available/inventory-management ]; then
    echo 'Une installation existe déjà ; arrêt sans remplacement.' >&2; exit 1
fi
apt-get update
apt-get install -y git unzip python3 php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath php8.2-intl
systemctl start mysql
# Resume safely when an earlier attempt created only the empty database.
TABLE_COUNT=$(mysql --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='inventory_management';")
if [ "$TABLE_COUNT" != 0 ]; then
    echo 'La base inventory_management contient déjà des tables ; arrêt sans modification.' >&2; exit 1
fi
USER_COUNT=$(mysql --batch --skip-column-names -e "SELECT COUNT(*) FROM mysql.user WHERE User='inventory_app' AND Host='127.0.0.1';")
if [ "$USER_COUNT" != 0 ]; then
    echo 'Le compte MySQL inventory_app existe déjà ; vérifier avant de reprendre.' >&2; exit 1
fi
DB_PASS="Aa1!$(openssl rand -hex 24)"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS inventory_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'inventory_app'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON inventory_management.* TO 'inventory_app'@'127.0.0.1';
SQL
mkdir -p /var/www
git clone https://github.com/webcreation-dev/inventory-management.git /var/www/inventory-management
cd /var/www/inventory-management
mkdir -p bootstrap/cache storage/app/public storage/framework/{cache/data,sessions,views} storage/logs
cp .env.example .env
export DB_PASS
python3 <<'PY'
from pathlib import Path
import os,re
p=Path('.env');s=p.read_text()
values={'APP_ENV':'production','APP_DEBUG':'false','APP_URL':'http://'+os.environ['INVENTORY_IP']+':5000','APP_INSTALLED':'false','APP_TIMEZONE':'Africa/Porto-Novo','DB_HOST':'127.0.0.1','DB_PORT':'3306','DB_DATABASE':'inventory_management','DB_USERNAME':'inventory_app','DB_PASSWORD':os.environ['DB_PASS'],'SESSION_DRIVER':'file','CACHE_STORE':'file','QUEUE_CONNECTION':'sync','ALLOW_RECOVERED_SCHEMA':'true','LOG_LEVEL':'error'}
for k,v in values.items():
 if re.search(r'^'+k+'=',s,re.M):s=re.sub(r'^'+k+'=.*$',k+'='+v,s,flags=re.M)
 else:s+='\n'+k+'='+v+'\n'
p.write_text(s)
PY
unset DB_PASS
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php8.2 artisan key:generate --force
php8.2 artisan migrate --force
php8.2 tools/vps-init.php
php8.2 artisan config:cache
php8.2 artisan view:cache
php8.2 artisan storage:link
chown -R root:www-data /var/www/inventory-management
chmod -R u=rwX,g=rX,o= /var/www/inventory-management
chmod -R g+rwX storage bootstrap/cache public/uploads
chmod 640 .env
cp deploy/nginx-port5000.conf /etc/nginx/sites-available/inventory-management
ln -s /etc/nginx/sites-available/inventory-management /etc/nginx/sites-enabled/inventory-management
nginx -t
systemctl enable --now php8.2-fpm
systemctl reload nginx
if command -v ufw >/dev/null && ufw status | grep -q '^Status: active'; then ufw allow 5000/tcp; fi
curl --fail --silent --output /dev/null "http://127.0.0.1:5000/login"
printf '\nApplication : http://%s:5000/login\n' "$INVENTORY_IP"
printf 'Identifiants : /root/inventory-admin.txt\n'
