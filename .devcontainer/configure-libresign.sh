#!/bin/bash
#
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Reconfigure LibreSign in the devcontainer. Safe to run after code changes or
# when occ was executed as root and broke appdata permissions.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "${SCRIPT_DIR}"

CONTAINER="${LIBRESIGN_DEVCONTAINER:-devcontainer-nextcloud-1}"

docker exec "${CONTAINER}" bash -lc '
chown -R www-data:www-data /var/www/html/data/appdata_* 2>/dev/null || true
runuser -u www-data -- occ app:enable libresign
runuser -u www-data -- occ libresign:install --use-local-cert --all
runuser -u www-data -- occ libresign:configure:openssl --cn=CommonName --c=BR --ou=OrganizationUnit --st=RioDeJaneiro --o=LibreSign --l=RioDeJaneiro
runuser -u www-data -- occ libresign:configure:check
'
