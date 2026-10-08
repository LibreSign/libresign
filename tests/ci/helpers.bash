# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Shared setup for the release packaging tests. Nextcloud, occ and the network
# are replaced by stubs that record what was executed; nothing outside
# BATS_TEST_TMPDIR is written.

setup_appstore_test() {
	REPO_ROOT="$(cd "$BATS_TEST_DIRNAME/../.." && pwd)"
	APP_NAME="$(basename "$REPO_ROOT")"

	NEXTCLOUD_DIR="$BATS_TEST_TMPDIR/nextcloud"
	CERT_DIR="$BATS_TEST_TMPDIR/certificates"
	STUB_BIN="$BATS_TEST_TMPDIR/bin"
	COMMAND_LOG="$BATS_TEST_TMPDIR/commands.log"
	mkdir -p "$NEXTCLOUD_DIR/apps" "$CERT_DIR" "$STUB_BIN"
	: > "$COMMAND_LOG"

	# occ records its arguments and fails for the subcommand named in OCC_FAIL_ON.
	cat > "$STUB_BIN/occ" <<-'EOF'
		#!/usr/bin/env bash
		printf 'occ %s\n' "$*" >> "$COMMAND_LOG"
		[ "$1" != "${OCC_FAIL_ON:-}" ]
	EOF
	# The signing steps of `appstore` download the certificate and sign the
	# archive; neither may reach the network or need a real key.
	cat > "$STUB_BIN/curl" <<-'EOF'
		#!/usr/bin/env bash
		printf 'curl %s\n' "$*" >> "$COMMAND_LOG"
	EOF
	cat > "$STUB_BIN/openssl" <<-'EOF'
		#!/usr/bin/env bash
		printf 'openssl %s\n' "$1" >> "$COMMAND_LOG"
	EOF
	chmod +x "$STUB_BIN"/*
	export COMMAND_LOG
}

with_signing_key() {
	touch "$CERT_DIR/$APP_NAME.key"
}

# Runs make like CI does: GITHUB_ACTIONS comes from the environment, never from
# the command line. Any value inherited from the runner is dropped first.
run_make() {
	local github_actions="$1" directory="$2"
	shift 2
	run env -u GITHUB_ACTIONS -u MAKEFLAGS -u MAKELEVEL -u MFLAGS \
		${github_actions:+GITHUB_ACTIONS="$github_actions"} \
		PATH="$STUB_BIN:$PATH" \
		make --no-print-directory -C "$directory" "$@" \
		cert_dir="$CERT_DIR" \
		nextcloud_directory="$NEXTCLOUD_DIR" \
		occ="$STUB_BIN/occ"
}

# Line of the first recorded call whose arguments start with "$1"; empty when
# the command never ran.
call_line() {
	grep -n -- "^$1\( \|\$\)" "$COMMAND_LOG" | head -n 1 | cut -d: -f1
}

ran() {
	[ -n "$(call_line "$1")" ]
}

# Creates a minimal app with the inputs `appstore` copies and links the real
# Makefile into it, so packaging runs without touching the repository. Prints
# the app directory.
make_app_fixture() {
	local app="$BATS_TEST_TMPDIR/work/$APP_NAME"
	mkdir -p "$app"/{appinfo,composer,css,img,js,l10n,lib,templates,vendor,3rdparty} \
		"$app/docs/changelogs" "$app/tests/php/fixtures/pdfs"
	printf '<info><id>libresign</id><version>1.0.0</version></info>\n' > "$app/appinfo/info.xml"
	printf '# Changelog\n' > "$app/docs/changelogs/changelog-1.md"
	for spec in openapi openapi-administration openapi-full; do
		printf '{}\n' > "$app/$spec.json"
	done
	touch "$app/js/pdf.worker.min-fixture.mjs" "$app/tests/php/fixtures/pdfs/small_valid.pdf"
	ln -s "$REPO_ROOT/Makefile" "$app/Makefile"
	printf '%s\n' "$app"
}
