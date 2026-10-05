#!/bin/sh
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -eu

repo_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
ncdd_dir="$repo_root/.devcontainer/.nextcloud-docker-development"
generated_compose="$repo_root/.devcontainer/ncdd.generated.yml"
ncdd_commit="b33c4dee93a4d41ce856e7480c871cf1171b3cbb"

prepare_ncdd() {
	if [ ! -d "$ncdd_dir/.git" ]; then
		rm -rf "$ncdd_dir"
		git clone \
			--filter=blob:none \
			--no-checkout \
			https://github.com/LibreCodeCoop/nextcloud-docker-development.git \
			"$ncdd_dir"
	fi

	if ! git -C "$ncdd_dir" cat-file -e "$ncdd_commit^{commit}" 2>/dev/null; then
		git -C "$ncdd_dir" fetch --depth=1 origin "$ncdd_commit"
	fi

	git -C "$ncdd_dir" checkout --detach "$ncdd_commit"
}

worker_id() {
	workspace_key="$(printf '%s' "$repo_root" | cksum | awk '{print $1}')"
	printf 'libresign-%s\n' "$workspace_key"
}

render_compose() {
	id="$1"
	tmp="$generated_compose.tmp.$$"
	trap 'rm -f "$tmp"' EXIT HUP INT TERM

	set -- env \
		HOST_UID="$(id -u)" \
		HOST_GID="$(id -g)" \
		DB_TYPE="${LIBRESIGN_DB_TYPE:-mariadb}" \
		MARIADB_VERSION="${LIBRESIGN_MARIADB_VERSION:-10.6}" \
		PHP_VERSION="${LIBRESIGN_PHP_VERSION:-83}" \
		VERSION_NEXTCLOUD="${LIBRESIGN_NEXTCLOUD_VERSION:-master}" \
		COMPOSE_PROFILES=playwright

	if [ "${CODESPACES:-}" = "true" ]; then
		: "${CODESPACE_NAME:?CODESPACE_NAME is required in Codespaces}"
		: "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:?GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN is required in Codespaces}"

		set -- "$@" \
			NEXTCLOUD_HOST="${CODESPACE_NAME}-443.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}" \
			NEXTCLOUD_PROTOCOL=https
	fi

	"$@" sh "$ncdd_dir/dev-worker" "$id" config > "$tmp"
	mv "$tmp" "$generated_compose"
	trap - EXIT HUP INT TERM
}

prepare_ncdd
render_compose "$(worker_id)"
