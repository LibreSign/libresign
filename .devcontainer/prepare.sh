#!/bin/sh
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -eu

repo_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
ncdd_dir="$repo_root/.devcontainer/.nextcloud-docker-development"
ncdd_commit="788437e228358b505b9fa90431f1d0aae1c08727"

if [ ! -d "$ncdd_dir/.git" ]; then
	rm -rf "$ncdd_dir"
	git clone --filter=blob:none --no-checkout 		https://github.com/LibreCodeCoop/nextcloud-docker-development.git 		"$ncdd_dir"
fi

git -C "$ncdd_dir" fetch --depth=1 origin "$ncdd_commit"
git -C "$ncdd_dir" checkout --detach "$ncdd_commit"

cat > "$ncdd_dir/.env" <<EOF
PHP_VERSION=${LIBRESIGN_PHP_VERSION:-83}
VERSION_NEXTCLOUD=${LIBRESIGN_NEXTCLOUD_VERSION:-master}
DB_TYPE=${LIBRESIGN_DB_TYPE:-mariadb}
MARIADB_VERSION=${LIBRESIGN_MARIADB_VERSION:-10.6}
EOF
