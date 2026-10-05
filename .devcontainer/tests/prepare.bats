#!/usr/bin/env bats
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

setup() {
	REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"
	PREPARE="$REPO_ROOT/.devcontainer/prepare.sh"
	GENERATED="$REPO_ROOT/.devcontainer/ncdd.generated.yml"
	OVERRIDE="$REPO_ROOT/.devcontainer/docker-compose.yml"
}

@test "prepare.sh renders a valid local NCDD configuration" {
	run sh "$PREPARE"
	[ "$status" -eq 0 ]

	run grep -q "HOST_UID: $(id -u)" "$GENERATED"
	[ "$status" -eq 0 ]

	run grep -q "HOST_GID: $(id -g)" "$GENERATED"
	[ "$status" -eq 0 ]

	run grep -Eq 'NEXTCLOUD_HOST: ncdev-.*\.localhost' "$GENERATED"
	[ "$status" -eq 0 ]

	run docker compose 		--file "$GENERATED" 		--file "$OVERRIDE" 		--profile playwright 		config --quiet
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
	run env 		LIBRESIGN_DB_TYPE=sqlite 		LIBRESIGN_NEXTCLOUD_VERSION=stable35 		sh "$PREPARE"
	[ "$status" -eq 0 ]

	run grep -q 'DB_TYPE: sqlite' "$GENERATED"
	[ "$status" -eq 0 ]

	run grep -q 'DB_DRIVER: sqlite' "$GENERATED"
	[ "$status" -eq 0 ]

	run grep -q 'VERSION_NEXTCLOUD: stable35' "$GENERATED"
	[ "$status" -eq 0 ]
}

@test "prepare.sh rejects incomplete Codespaces context" {
	run env CODESPACES=true sh "$PREPARE"

	[ "$status" -ne 0 ]
	[[ "$output" == *"CODESPACE_NAME is required in Codespaces"* ]]
}

@test "prepare.sh renders the Codespaces public Nextcloud URL" {
	run env 		CODESPACES=true 		CODESPACE_NAME=libresign-test 		GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN=app.github.dev 		sh "$PREPARE"
	[ "$status" -eq 0 ]

	run grep -q 'NEXTCLOUD_HOST: libresign-test-443.app.github.dev' "$GENERATED"
	[ "$status" -eq 0 ]

	run grep -q 'NEXTCLOUD_PROTOCOL: https' "$GENERATED"
	[ "$status" -eq 0 ]
}
