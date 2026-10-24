#!/usr/bin/env node
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * PoC bootstrap for running LibreSign's Playwright suite against the current
 * Nextcloud E2E test architecture (@nextcloud/e2e-test-server).
 *
 * See https://github.com/LibreSign/libresign/issues/8727 for the design brief.
 *
 * This script owns the full server lifecycle for a single Playwright run:
 *   1. Select the Nextcloud branch requested by the test environment
 *      (NEXTCLOUD_BRANCH, defaults to `master`).
 *   2. Start the disposable Nextcloud container with startNextcloud().
 *   3. Expose a stable local port (PORT env var, defaults to 8080).
 *   4. Wait for Nextcloud with waitOnNextcloud().
 *   5. Prepare the Nextcloud instance (occ config + app enables).
 *   6. Prepare LibreSign (libresign:install with --java/--jsignpdf/--pdftk).
 *   7. Print one deterministic ready message.
 *   8. Keep the process alive while Playwright is running.
 *   9. Handle SIGTERM / SIGINT by calling stopNextcloud().
 *  10. Exit cleanly when Playwright closes stdin.
 *
 * It is intentionally minimal: no HTTP router (Playwright reaches Nextcloud
 * directly via baseURL), no retries (the maintainer's spec explicitly forbids
 * auto-restart to avoid masking the intermittent server-crash investigation),
 * and no second custom test framework.
 */

import { startNextcloud, stopNextcloud, waitOnNextcloud, runOcc } from "@nextcloud/e2e-test-server/docker";
import { setTimeout as sleep } from "node:timers/promises";

const NEXTCLOUD_BRANCH = process.env.NEXTCLOUD_BRANCH ?? "master";
const PORT = Number(process.env.PORT ?? 8080);
const READY_MESSAGE = "LibreSign Nextcloud container ready for Playwright";
const APP_NAME = "libresign";

let stopping = false;
let stopped = false;

async function installLibreSignRuntime() {
	// These commands mirror the current `playwright.yml` workflow steps that
	// run inside the disposable container. The exact command set is documented
	// in the PR's A/B comparison table.
	await runOcc(["app:enable", "--force", "notifications"], { verbose: true });
	await runOcc(["app:enable", "--force", "activity"], { verbose: true });
	await runOcc(["app:enable", "--force", APP_NAME], { verbose: true });
	await runOcc(["config:system:set", "allow_local_remote_servers", "--value", "true", "--type", "boolean"]);
	await runOcc(["config:system:set", "auth.bruteforce.protection.enabled", "--value", "false", "--type", "boolean"]);
	await runOcc(["config:system:set", "ratelimit.protection.enabled", "--value", "false", "--type", "boolean"]);
	await runOcc(["config:system:set", "debug", "--value", "true", "--type", "boolean"]);
	await runOcc(["config:system:set", "overwrite.cli.url", "--value", `http://localhost:${PORT}`]);
	await runOcc(["config:system:set", "overwritehost", "--value", `localhost:${PORT}`]);
	await runOcc(["user:setting", "admin", "settings", "email", "admin@email.tld"]);
	await runOcc(["libresign:install", "--use-local-cert", "--java"]);
	await runOcc(["libresign:install", "--use-local-cert", "--jsignpdf"]);
	await runOcc(["libresign:install", "--use-local-cert", "--pdftk"]);
	await runOcc([
		"libresign:configure:openssl",
		"--cn=Common Name",
		"--c=BR",
		"--ou=Organization Unit",
		"--st=Rio de Janeiro",
		"--o=LibreSign",
		"--l=Rio de Janeiro",
	]);
}

async function shutdown(reason) {
	if (stopped) {
		return;
	}
	stopped = true;
	console.error(`[bootstrap] shutting down: ${reason}`);
	try {
		await stopNextcloud({ saveLogTo: process.env.NEXTCLOUD_E2E_LOG_FILE });
	} catch (err) {
		console.error("[bootstrap] stopNextcloud failed:", err);
	}
	process.exit(0);
}

process.on("SIGTERM", () => { stopping = true; void shutdown("SIGTERM"); });
process.on("SIGINT", () => { stopping = true; void shutdown("SIGINT"); });
process.on("uncaughtException", (err) => {
	console.error("[bootstrap] uncaughtException:", err);
	void shutdown("uncaughtException");
});

async function main() {
	console.log(`[bootstrap] starting Nextcloud container (branch=${NEXTCLOUD_BRANCH}, port=${PORT})`);

	// mountApp=true auto-detects LibreSign source from the cwd (looks for
	// appinfo/info.xml walking up). The container is started with exposePort
	// so Playwright's baseURL can reach it on the host.
	const ip = await startNextcloud(NEXTCLOUD_BRANCH, true, {
		exposePort: PORT,
	});

	console.log(`[bootstrap] Nextcloud IP=${ip}, waiting for ready…`);
	await waitOnNextcloud(ip);

	// SQLite WAL mode is the project's documented default for tests. The
	// current e2e-test-server API does not enable it automatically, so we set
	// it via occ after waitOnNextcloud().
	await runOcc(["config:system:set", "sqlite.journal_mode", "--value", "wal"], { verbose: true });

	await installLibreSignRuntime();

	// Single, deterministic readiness line. Playwright's webServer waits for
	// this exact stdout before starting the test suite.
	process.stdout.write(`${READY_MESSAGE}\n`);

	// Keep the process alive while Playwright runs.
	// We do NOT spawn an HTTP router: Playwright reaches Nextcloud directly via
	// baseURL. The PHP router in playwright/router.php remains the existing
	// setup and is not used by this PoC.
	while (!stopping) {
		await sleep(1000);
	}
}

main().catch((err) => {
	console.error("[bootstrap] fatal:", err);
	void shutdown("fatal error");
});
