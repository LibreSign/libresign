/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Playwright configuration that uses the @nextcloud/e2e-test-server bootstrap.
 *
 * This is the PoC configuration for issue #8727 ("PoC: align the Playwright
 * test environment with the Nextcloud E2E pattern"). The existing
 * `playwright.config.ts` is intentionally untouched and remains the production
 * configuration until the PoC is adopted.
 *
 * Run with: npx playwright test --config playwright.config.e2e-poc.ts
 */

import { defineConfig, devices } from "@playwright/test";

const isCI = !!process.env.CI;

export default defineConfig({
	testDir: "./playwright/e2e",

	/* Don't forbid test.only locally: this PoC is for evaluation. */
	forbidOnly: isCI,

	/* One worker in CI (same as production config). No sharding. */
	workers: isCI ? 1 : undefined,

	/* Same reporter behavior as production. */
	reporter: isCI ? [["list"], ["github"]] : "list",

	timeout: 60_000,

	use: {
		/* The bootstrap script exposes Nextcloud on this port. */
		baseURL: process.env.PLAYWRIGHT_BASE_URL ?? "http://localhost:8080",
		locale: "en-US",
		extraHTTPHeaders: {
			"Accept-Language": "en-US,en;q=0.9",
		},
		ignoreHTTPSErrors: true,
		trace: "on-first-retry",
		screenshot: "only-on-failure",
	},

	/* Playwright owns the server lifecycle. */
	webServer: {
		command: "node playwright/start-nextcloud-server.mjs",
		stdout: "pipe",
		stderr: "pipe",
		/* The bootstrap prints one deterministic ready message; Playwright
		 * must wait for it. */
		wait: {
			stdout: /LibreSign Nextcloud container ready for Playwright/,
		},
		/* Nextcloud container + LibreSign install can take several minutes on
		 * cold start. */
		timeout: 600_000,
		/* Fresh environment per CI run; local runs may reuse an existing one
		 * when startNextcloud() finds it. */
		reuseExistingServer: !isCI,
		/* Graceful shutdown via SIGTERM (also handles Docker containers). */
		gracefulShutdown: {
			signal: "SIGTERM",
			timeout: 30_000,
		},
	},

	projects: [
		{
			name: "chromium",
			use: {
				...devices["Desktop Chrome"],
			},
		},
	],
});
