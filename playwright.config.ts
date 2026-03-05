/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineConfig, devices } from '@playwright/test'

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
	testDir: './playwright',

	/* Run tests in files in parallel */
	fullyParallel: true,
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !!process.env.CI,
	/* Retry on CI only */
	retries: process.env.CI ? 2 : 0,
	/* Opt out of parallel tests on CI. */
	workers: process.env.CI ? 1 : undefined,
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: process.env.CI ? 'github' : 'list',
	/* Default timeout for each test (60 seconds) */
	timeout: 60000,

	/* Shared settings for all the projects below. */
	use: {
		/* Base URL to use in actions like `await page.goto('./apps/libresign')`. */
		baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'https://localhost',

		/* Ignore HTTPS errors on local self-signed certificates */
		ignoreHTTPSErrors: true,

		/* Collect trace when retrying the failed test. */
		trace: 'on-first-retry',

		/* Screenshot on failure */
		screenshot: 'only-on-failure',
	},

	projects: [
		{
			name: 'chromium',
			use: {
				...devices['Desktop Chrome'],
			},
		},
	],
})
