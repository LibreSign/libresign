#!/usr/bin/env bats
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

setup() {
	REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"
	# shellcheck source=../setup.sh
	source "$REPO_ROOT/.devcontainer/setup.sh"
}

@test "environment summary shows the canonical Nextcloud URL and internal Mailpit endpoint" {
	run env 		NEXTCLOUD_PROTOCOL=https 		NEXTCLOUD_HOST=ncdev-libresign-test.localhost 		COMPOSE_PROJECT_NAME=ncdev-libresign-test 		NEXTCLOUD_ADMIN_USER=admin 		VERSION_NEXTCLOUD=master 		bash -c 'source "$1"; environment_summary' _ "$REPO_ROOT/.devcontainer/setup.sh"

	[ "$status" -eq 0 ]
	[[ "$output" == *"LibreSign environment ready"* ]]
	[[ "$output" == *"Nextcloud / LibreSign"*"https://ncdev-libresign-test.localhost"* ]]
	[[ "$output" == *"Mailpit (environment)"*"http://mailpit:8025"* ]]
	[[ "$output" == *"Mailpit (browser)"*"https://ncdev-libresign-test-mailpit.localhost"* ]]
	[[ "$output" == *"Nextcloud branch"*"master"* ]]
	[[ "$output" == *"Useful commands"* ]]
}

@test "environment summary does not advertise a localhost Mailpit browser URL for a remote host" {
	run env 		NEXTCLOUD_PROTOCOL=https 		NEXTCLOUD_HOST=example-443.app.github.dev 		COMPOSE_PROJECT_NAME=ncdev-libresign-test 		bash -c 'source "$1"; environment_summary' _ "$REPO_ROOT/.devcontainer/setup.sh"

	[ "$status" -eq 0 ]
	[[ "$output" == *"Nextcloud / LibreSign"*"https://example-443.app.github.dev"* ]]
	[[ "$output" == *"Mailpit (environment)"*"http://mailpit:8025"* ]]
	[[ "$output" != *"Mailpit (browser):"* ]]
}
