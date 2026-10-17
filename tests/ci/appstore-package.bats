#!/usr/bin/env bats
# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Behavior of `make verify-appstore-package` against the archive built by
# `make appstore` without a signing key, as on pull requests.

load helpers

setup() {
	setup_appstore_test
	APP="$(make_app_fixture)"
	SIGN_DIR="$APP/build/artifacts/sign"
	PACKAGE="$APP/build/artifacts/$APP_NAME.tar.gz"
}

# Rebuilds the archive from the staging directory after the test altered it.
repack() {
	tar -czf "$PACKAGE" -C "$SIGN_DIR" "$APP_NAME"
}

@test "builds and accepts the unsigned package" {
	run_make true "$APP" appstore verify-appstore-package

	[ "$status" -eq 0 ]
	[ -f "$PACKAGE" ]
	[ ! -s "$COMMAND_LOG" ]
	tar -tzf "$PACKAGE" | grep -qx "$APP_NAME/appinfo/info.xml"
}

@test "rejects a package that ships a development path" {
	run_make true "$APP" appstore
	[ "$status" -eq 0 ]
	mkdir -p "$SIGN_DIR/$APP_NAME/src"
	touch "$SIGN_DIR/$APP_NAME/src/main.ts"
	repack

	run_make true "$APP" verify-appstore-package

	[ "$status" -ne 0 ]
	[[ "$output" == *"must not contain src"* ]]
}

@test "rejects a package that is missing a required path" {
	run_make true "$APP" appstore
	[ "$status" -eq 0 ]
	rm -rf "$SIGN_DIR/$APP_NAME/templates"
	repack

	run_make true "$APP" verify-appstore-package

	[ "$status" -ne 0 ]
	[[ "$output" == *"missing templates"* ]]
}
