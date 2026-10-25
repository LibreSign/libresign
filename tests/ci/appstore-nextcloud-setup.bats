#!/usr/bin/env bats
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Behavior of the Nextcloud initialization that `make appstore` runs before
# signing a release.

load helpers

setup() {
	setup_appstore_test
}

@test "initializes Nextcloud on GitHub Actions when the signing key is present" {
	with_signing_key

	run_make true "$REPO_ROOT" _appstore-nextcloud-setup

	[ "$status" -eq 0 ]
	[ -d "$NEXTCLOUD_DIR/data" ]
	[ "$(readlink "$NEXTCLOUD_DIR/apps/libresign")" = "$REPO_ROOT" ]
	ran 'occ maintenance:install'
	grep -q -- '^occ maintenance:install .*--database=sqlite' "$COMMAND_LOG"
	ran 'occ --version'
	ran 'occ app:enable --force libresign'
	[ "$(call_line 'occ maintenance:install')" -lt "$(call_line 'occ --version')" ]
	[ "$(call_line 'occ --version')" -lt "$(call_line 'occ app:enable')" ]
}

@test "skips the initialization outside GitHub Actions" {
	with_signing_key

	run_make '' "$REPO_ROOT" _appstore-nextcloud-setup

	[ "$status" -eq 0 ]
	[ ! -s "$COMMAND_LOG" ]
	[ ! -e "$NEXTCLOUD_DIR/data" ]
	[ ! -e "$NEXTCLOUD_DIR/apps/libresign" ]
}

@test "skips the initialization when the signing key is absent" {
	run_make true "$REPO_ROOT" _appstore-nextcloud-setup

	[ "$status" -eq 0 ]
	[ ! -s "$COMMAND_LOG" ]
	[ ! -e "$NEXTCLOUD_DIR/data" ]
	[ ! -e "$NEXTCLOUD_DIR/apps/libresign" ]
}

@test "stops with an error when a required initialization command fails" {
	with_signing_key
	export OCC_FAIL_ON=maintenance:install

	run_make true "$REPO_ROOT" _appstore-nextcloud-setup

	[ "$status" -ne 0 ]
	ran 'occ maintenance:install'
	[ -z "$(call_line 'occ app:enable')" ]
}

@test "appstore initializes Nextcloud before signing the release" {
	local app
	app="$(make_app_fixture)"
	with_signing_key

	run_make true "$app" appstore

	[ "$status" -eq 0 ]
	[ -f "$app/build/artifacts/$APP_NAME.tar.gz" ]
	[ "$(readlink "$NEXTCLOUD_DIR/apps/libresign")" = "$app" ]
	ran 'occ maintenance:install'
	ran 'occ app:enable --force libresign'
	ran 'occ integrity:sign-app'
	[ "$(call_line 'occ app:enable')" -lt "$(call_line 'occ libresign:install')" ]
	[ "$(call_line 'occ app:enable')" -lt "$(call_line 'occ integrity:sign-app')" ]
}
