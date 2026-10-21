#!/bin/sh
set -eu
cd "$(dirname "$0")"
MYSQL_BIN=/opt/homebrew/opt/mysql/bin
if ! "$MYSQL_BIN/mysqladmin" --no-defaults --protocol=tcp --host=127.0.0.1 --port=3307 --user=root ping >/dev/null 2>&1; then
    if [ ! -d storage/mysql-local/mysql ]; then
        echo 'La base locale manque : initialiser storage/mysql-local avant le lancement.' >&2
        exit 1
    fi
    "$MYSQL_BIN/mysqld" --no-defaults --datadir="$PWD/storage/mysql-local" --bind-address=127.0.0.1 --port=3307 --socket=/tmp/offpos-mysql.sock --mysqlx=OFF >storage/logs/mysql-local.log 2>&1 &
    attempts=0
    until "$MYSQL_BIN/mysqladmin" --no-defaults --protocol=tcp --host=127.0.0.1 --port=3307 --user=root ping >/dev/null 2>&1; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge 20 ]; then
            echo 'MySQL ne démarre pas. Voir storage/logs/mysql-local.log.' >&2
            exit 1
        fi
        sleep 1
    done
fi
exec /opt/homebrew/opt/php@8.2/bin/php artisan serve --host=127.0.0.1 --port=8000
