#!/bin/bash
# SPDX-FileCopyrightText: 2024-2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -Eeo pipefail

app_dir=/var/www/html/apps-extra/libresign

wait_for_nextcloud() {
	for _ in {1..120}; do
		if occ status 2>/dev/null | grep -q 'installed: true'; then
			return 0
		fi
		sleep 1
	done

	echo "Nextcloud did not become ready within 120 seconds." >&2
	return 1
}

add_safe_directory() {
	local path="$1"

	if ! git config --global --get-all safe.directory | grep -Fxq "$path"; then
		git config --global --add safe.directory "$path"
	fi
}

environment_summary() {
	local protocol="${NEXTCLOUD_PROTOCOL:-https}"
	local nextcloud_host="${NEXTCLOUD_HOST:-localhost}"

	printf '\n'
	printf 'LibreSign development environment is ready.\n'
	printf '\n'
	printf '  Nextcloud / LibreSign: %s://%s\n' "$protocol" "$nextcloud_host"
	printf '  Mailpit (environment): http://mailpit:8025\n'

	if [[ "$nextcloud_host" == *.localhost ]] && [[ -n "${COMPOSE_PROJECT_NAME:-}" ]]; then
		printf '  Mailpit (browser):     https://%s-mailpit.localhost\n' "$COMPOSE_PROJECT_NAME"
	fi

	printf '  Admin user:            %s\n' "${NEXTCLOUD_ADMIN_USER:-admin}"
	printf '  Nextcloud branch:      %s\n' "${VERSION_NEXTCLOUD:-master}"
	printf '\n'
	printf 'Useful commands:\n'
	printf '  npm run watch          rebuild frontend assets while editing\n'
	printf '  occ status             check Nextcloud status\n'
	printf '  occ app:list           inspect enabled apps\n'
	printf '\n'
	printf 'The environment is ready for development.\n'
}

main() {
	wait_for_nextcloud
	add_safe_directory /var/www/html
	add_safe_directory "$app_dir"

	cd "$app_dir"
	git submodule update --init --recursive

	composer install --no-interaction
	npm ci

	occ app:enable libresign
	occ libresign:install --use-local-cert --java
	occ libresign:install --use-local-cert --pdftk
	occ libresign:install --use-local-cert --jsignpdf
	occ libresign:configure:openssl \
		--cn=CommonName \
		--c=BR \
		--ou=OrganizationUnit \
		--st=RioDeJaneiro \
		--o=LibreSign \
		--l=RioDeJaneiro

	occ theming:config name "LibreSign"
	occ theming:config url "https://libresign.coop"
	occ theming:config primary_color "#144042"
	occ config:app:set libresign extra_settings --value=1
	occ config:system:set defaultapp --value libresign
	occ maintenance:theme:update

	npm run dev

	environment_summary
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
	main "$@"
fi
