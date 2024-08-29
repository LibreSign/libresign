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
	occ theming:config name "LibreSign"
	occ theming:config url "https://libresign.coop"
	occ theming:config primary_color "#144042"
	occ config:app:set libresign extra_settings --value=1
	occ config:system:set defaultapp --value libresign
	occ maintenance:theme:update
	npm ci
	npm run dev
fi
echo "✍️ LibreSign is up!"
echo "If you want to develop at frontend, run the command 'npm run watch'"
