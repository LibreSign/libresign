#!/usr/bin/env bats
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

setup() {
	REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"
	PREPARE="$REPO_ROOT/.devcontainer/prepare.sh"
	GENERATED="$REPO_ROOT/.devcontainer/ncdd.generated.yml"
	OVERRIDE="$REPO_ROOT/.devcontainer/docker-compose.yml"
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
