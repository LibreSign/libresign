#!/bin/sh
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -eu

repo_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
ncdd_dir="$repo_root/.devcontainer/.nextcloud-docker-development"
generated_compose="$repo_root/.devcontainer/ncdd.generated.yml"
worker_id_file="$repo_root/.devcontainer/.worker-id"
ncdd_commit="ef366f2f7b5e3ce3bd34b90d6d5fe25f90dff35b"

if [ ! -d "$ncdd_dir/.git" ]; then
	rm -rf "$ncdd_dir"
	git clone --filter=blob:none --no-checkout 		https://github.com/LibreCodeCoop/nextcloud-docker-development.git 		"$ncdd_dir"
fi

git -C "$ncdd_dir" fetch --depth=1 origin "$ncdd_commit"
git -C "$ncdd_dir" checkout --detach "$ncdd_commit"

workspace_key="$(printf '%s' "$repo_root" | cksum | awk '{print $1}')"
worker_id="libresign-$workspace_key"
printf '%s\n' "$worker_id" > "$worker_id_file"

set -- env 	DB_TYPE="${LIBRESIGN_DB_TYPE:-mariadb}" 	MARIADB_VERSION="${LIBRESIGN_MARIADB_VERSION:-10.6}" 	PHP_VERSION="${LIBRESIGN_PHP_VERSION:-83}" 	VERSION_NEXTCLOUD="${LIBRESIGN_NEXTCLOUD_VERSION:-master}"

if [ "${CODESPACES:-}" = "true" ]; then
	: "${CODESPACE_NAME:?CODESPACE_NAME is required in Codespaces}"
	: "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:?GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN is required in Codespaces}"

	codespaces_port="${LIBRESIGN_CODESPACES_PORT:-8080}"
	codespaces_host="${CODESPACE_NAME}-${codespaces_port}.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"

	set -- "$@" 		NEXTCLOUD_HOST="$codespaces_host" 		NEXTCLOUD_PROTOCOL=https 		NEXTCLOUD_PORT="$codespaces_port"
fi

"$@" sh "$ncdd_dir/dev-worker" "$worker_id" config > "$generated_compose"
