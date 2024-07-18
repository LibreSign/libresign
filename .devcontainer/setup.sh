#!/bin/bash

if [ ! -e /var/www/html/apps-extra/libresign  ]; then
	ln -s /var/www/libresign /var/www/html/apps-extra/libresign
fi

cd /var/www/html/apps-extra/libresign
if [[ ! -d "vendor" ]]; then
	composer install
fi
occ app:enable libresign
if [[ ! -d "node_modules" ]]; then
	npm ci
	npm run dev
fi
echo "LibreSign is up!"
echo "If you want to develop at frontend, run the command 'npm run watch'"
