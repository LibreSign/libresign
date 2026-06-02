#!/bin/bash
#
# SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#

(
    . /var/www/scripts/entrypoint.sh && php-fpm --daemonize

)

git config --global --add safe.directory /var/www/html
git config --global --add safe.directory /var/www/html/apps-extra/libresign
cd /var/www/html/apps-extra/libresign
git submodule update --init --recursive
if [[ ! -d "vendor" ]]; then
	composer install
fi
occ app:enable libresign
occ libresign:install --use-local-cert --java
occ libresign:install --use-local-cert --pdftk
occ libresign:install --use-local-cert --jsignpdf
occ libresign:configure:openssl --cn=CommonName --c=BR --ou=OrganizationUnit --st=RioDeJaneiro --o=LibreSign --l=RioDeJaneiro
if [[ ! -d "node_modules" ]]; then
	occ theming:config name "GoPaperless"
	occ theming:config url "https://gopaperless.ke"
	occ theming:config slogan "Simplify your paperwork. Sign, seal and deliver"
	occ theming:config legal_notice_url "https://tendaworld.com/terms-of-service/"
	occ theming:config privacy_policy_url "https://tendaworld.com/privacy-policy/"
	occ theming:config color "#04D56D"
	occ theming:config background_color "#0F172A"
	occ theming:config background_image none
	occ config:app:set libresign extra_settings --value=1
	occ config:system:set defaultapp --value libresign
	occ maintenance:theme:update
	npm ci
	npm run dev
fi
echo "✍️ LibreSign is up!"
echo "If you want to develop at frontend, run the command 'npm run watch'"
