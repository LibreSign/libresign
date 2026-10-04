#!/bin/bash
# SPDX-FileCopyrightText: 2024-2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -Eeo pipefail

app_dir=/var/www/html/apps-extra/libresign

for _ in {1..120}; do
	if occ status 2>/dev/null | grep -q 'installed: true'; then
		break
	fi
	sleep 1
done

if ! occ status 2>/dev/null | grep -q 'installed: true'; then
	echo "Nextcloud did not become ready within 120 seconds." >&2
	exit 1
fi

git config --global --add safe.directory /var/www/html
git config --global --add safe.directory "$app_dir"

cd "$app_dir"
git submodule update --init --recursive

if [[ ! -d vendor ]]; then
	composer install
fi

occ app:enable libresign
occ libresign:install --use-local-cert --java
occ libresign:install --use-local-cert --pdftk
occ libresign:install --use-local-cert --jsignpdf
occ libresign:configure:openssl 	--cn=CommonName 	--c=BR 	--ou=OrganizationUnit 	--st=RioDeJaneiro 	--o=LibreSign 	--l=RioDeJaneiro

if [[ ! -d node_modules ]]; then
	occ theming:config name "LibreSign"
	occ theming:config url "https://libresign.coop"
	occ theming:config primary_color "#144042"
	occ config:app:set libresign extra_settings --value=1
	occ config:system:set defaultapp --value libresign
	occ maintenance:theme:update
	npm ci
	npm run dev
fi

echo "LibreSign is ready at https://${NEXTCLOUD_HOST}"
echo "For frontend development, run: npm run watch"
