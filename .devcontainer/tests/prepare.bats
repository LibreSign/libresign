#!/usr/bin/env bats
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

setup() {
	REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"
	PREPARE="$REPO_ROOT/.devcontainer/prepare.sh"
	GENERATED="$REPO_ROOT/.devcontainer/ncdd.generated.yml"
	OVERRIDE="$REPO_ROOT/.devcontainer/docker-compose.yml"
	TEMP_CHECKOUT_ROOT=""
	TEMP_WORKER_A=""
	TEMP_WORKER_B=""
}

teardown() {
	if [ -n "$TEMP_CHECKOUT_ROOT" ]; then
		rm -rf "$TEMP_CHECKOUT_ROOT"
	fi

	ncdd="$REPO_ROOT/.devcontainer/.nextcloud-docker-development"
	if [ -n "$TEMP_WORKER_A" ]; then
		rm -rf "$ncdd/.workers/$TEMP_WORKER_A"
	fi
	if [ -n "$TEMP_WORKER_B" ]; then
		rm -rf "$ncdd/.workers/$TEMP_WORKER_B"
	fi
}

compose_json() {
	docker compose \
		--file "$GENERATED" \
		--file "$OVERRIDE" \
		--profile playwright \
		config --format json
}

@test "prepare.sh renders a valid local NCDD configuration" {
	run sh "$PREPARE"
	[ "$status" -eq 0 ]

	run docker compose \
		--file "$GENERATED" \
		--file "$OVERRIDE" \
		--profile playwright \
		config --quiet
	[ "$status" -eq 0 ]

	config="$(compose_json)"

	run jq -e --arg uid "$(id -u)" \
		'.services.nextcloud.environment.HOST_UID == $uid' <<<"$config"
	[ "$status" -eq 0 ]

	run jq -e --arg gid "$(id -g)" \
		'.services.nextcloud.environment.HOST_GID == $gid' <<<"$config"
	[ "$status" -eq 0 ]

	run jq -e \
		'.services.nextcloud.environment.NEXTCLOUD_HOST
		 | test("^ncdev-.*\\.localhost$")' <<<"$config"
	[ "$status" -eq 0 ]
}

@test "prepare.sh is idempotent for the same checkout and environment" {
	run sh "$PREPARE"
	[ "$status" -eq 0 ]
	first="$(sha256sum "$GENERATED" | awk '{print $1}')"

	run sh "$PREPARE"
	[ "$status" -eq 0 ]
	second="$(sha256sum "$GENERATED" | awk '{print $1}')"

	[ "$first" = "$second" ]
}

@test "prepare.sh forwards supported LibreSign overrides to NCDD" {
	run env \
		LIBRESIGN_DB_TYPE=sqlite \
		LIBRESIGN_NEXTCLOUD_VERSION=stable35 \
		sh "$PREPARE"
	[ "$status" -eq 0 ]

	config="$(compose_json)"

	run jq -e \
		'.services.nextcloud.environment.DB_TYPE == "sqlite"' <<<"$config"
	[ "$status" -eq 0 ]

	run jq -e \
		'.services.nextcloud.environment.DB_DRIVER == "sqlite"' <<<"$config"
	[ "$status" -eq 0 ]

	run jq -e \
		'.services.nextcloud.environment.VERSION_NEXTCLOUD == "stable35"' <<<"$config"
	[ "$status" -eq 0 ]
}

@test "prepare.sh keeps the last valid configuration when rendering fails" {
	run sh "$PREPARE"
	[ "$status" -eq 0 ]
	before="$(sha256sum "$GENERATED" | awk '{print $1}')"

	run env LIBRESIGN_DB_TYPE=unsupported sh "$PREPARE"
	[ "$status" -ne 0 ]

	after="$(sha256sum "$GENERATED" | awk '{print $1}')"
	[ "$before" = "$after" ]
}

@test "prepare.sh rejects incomplete Codespaces context" {
	run env CODESPACES=true sh "$PREPARE"

	[ "$status" -ne 0 ]
	[[ "$output" == *"CODESPACE_NAME is required in Codespaces"* ]]
}

@test "prepare.sh renders the Codespaces public Nextcloud URL" {
	run env \
		CODESPACES=true \
		CODESPACE_NAME=libresign-test \
		GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN=app.github.dev \
		sh "$PREPARE"
	[ "$status" -eq 0 ]

	config="$(compose_json)"

	run jq -e \
		'.services.nextcloud.environment.NEXTCLOUD_HOST
		 == "libresign-test-443.app.github.dev"' <<<"$config"
	[ "$status" -eq 0 ]

	run jq -e \
		'.services.nextcloud.environment.NEXTCLOUD_PROTOCOL == "https"' <<<"$config"
	[ "$status" -eq 0 ]
}

@test "separate checkout paths produce isolated NCDD projects" {
	run sh "$PREPARE"
	[ "$status" -eq 0 ]

	TEMP_CHECKOUT_ROOT="$(mktemp -d)"
	checkout_a="$TEMP_CHECKOUT_ROOT/checkout-a"
	checkout_b="$TEMP_CHECKOUT_ROOT/checkout-b"
	ncdd="$REPO_ROOT/.devcontainer/.nextcloud-docker-development"

	for checkout in "$checkout_a" "$checkout_b"; do
		mkdir -p "$checkout/.devcontainer"
		cp "$PREPARE" "$checkout/.devcontainer/prepare.sh"
		cp "$OVERRIDE" "$checkout/.devcontainer/docker-compose.yml"
		ln -s "$ncdd" "$checkout/.devcontainer/.nextcloud-docker-development"
	done

	TEMP_WORKER_A="libresign-$(printf '%s' "$checkout_a" | cksum | awk '{print $1}')"
	TEMP_WORKER_B="libresign-$(printf '%s' "$checkout_b" | cksum | awk '{print $1}')"

	run sh "$checkout_a/.devcontainer/prepare.sh"
	[ "$status" -eq 0 ]
	run sh "$checkout_b/.devcontainer/prepare.sh"
	[ "$status" -eq 0 ]

	config_a="$(docker compose \
		--file "$checkout_a/.devcontainer/ncdd.generated.yml" \
		--file "$checkout_a/.devcontainer/docker-compose.yml" \
		--profile playwright config --format json)"
	config_b="$(docker compose \
		--file "$checkout_b/.devcontainer/ncdd.generated.yml" \
		--file "$checkout_b/.devcontainer/docker-compose.yml" \
		--profile playwright config --format json)"

	project_a="$(jq -r '.name' <<<"$config_a")"
	project_b="$(jq -r '.name' <<<"$config_b")"
	host_a="$(jq -r '.services.nextcloud.environment.NEXTCLOUD_HOST' <<<"$config_a")"
	host_b="$(jq -r '.services.nextcloud.environment.NEXTCLOUD_HOST' <<<"$config_b")"

	[ "$TEMP_WORKER_A" != "$TEMP_WORKER_B" ]
	[ "$project_a" = "ncdev-$TEMP_WORKER_A" ]
	[ "$project_b" = "ncdev-$TEMP_WORKER_B" ]
	[ "$project_a" != "$project_b" ]
	[ "$host_a" != "$host_b" ]
}
