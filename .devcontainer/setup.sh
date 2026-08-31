#!/bin/bash
#
# SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#

(
    . /var/www/scripts/entrypoint.sh && php-fpm --daemonize

)

# Root-owned appdata breaks occ/libresign:install when agents run docker exec as root.
if [[ "$(id -u)" -eq 0 ]]; then
	chown -R www-data:www-data /var/www/html/data/appdata_* 2>/dev/null || true
fi

run_occ() {
	if [[ "$(id -u)" -eq 0 ]]; then
		runuser -u www-data -- occ "$@"
	else
		occ "$@"
	fi
}

git config --global --add safe.directory /var/www/html
git config --global --add safe.directory /var/www/html/apps-extra/libresign
cd /var/www/html/apps-extra/libresign
git submodule update --init --recursive
if [[ ! -d "vendor" ]]; then
	composer install
fi
run_occ app:enable libresign
run_occ libresign:install --use-local-cert --all
run_occ libresign:configure:openssl --cn=CommonName --c=BR --ou=OrganizationUnit --st=RioDeJaneiro --o=LibreSign --l=RioDeJaneiro
run_occ libresign:configure:check
if [[ ! -d "node_modules" ]]; then
	run_occ theming:config name "LibreSign"
	run_occ theming:config url "https://libresign.coop"
	run_occ theming:config primary_color "#144042"
	run_occ config:app:set libresign extra_settings --value=1
	run_occ config:system:set defaultapp --value libresign
	run_occ maintenance:theme:update
	npm ci
	npm run dev
fi
echo "✍️ LibreSign is up!"
echo "If you want to develop at frontend, run the command 'npm run watch'"
