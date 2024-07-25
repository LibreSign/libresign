#!/bin/bash

(
    . /var/www/scripts/entrypoint.sh && php-fpm --daemonize
)

git config --global --add safe.directory /var/www/html
git config --global --add safe.directory /var/www/html/apps-extra/libresign
cd /var/www/html/apps-extra/libresign
if [[ ! -d "vendor" ]]; then
	composer install
fi
occ app:enable libresign
if [[ ! -d "node_modules" ]]; then
	npm ci
	npm run dev
fi
echo "✍️ LibreSign is up!"
echo "If you want to develop at frontend, run the command 'npm run watch'"
