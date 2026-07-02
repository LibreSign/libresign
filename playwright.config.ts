/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineConfig, devices } from '@playwright/test'

const isCI = !!process.env.CI
const forceFastFail = process.env.PLAYWRIGHT_FAST_FAIL === '1'
const isDevelopmentFastFail = forceFastFail || (!isCI && process.env.PLAYWRIGHT_FAST_FAIL !== '0')

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
	testDir: './playwright/e2e',

	/* Run tests in files in parallel */
	fullyParallel: true,
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: isCI,
	/* Retry on CI only, unless fast-fail is explicitly forced for local debugging. */
	retries: isDevelopmentFastFail ? 0 : (isCI ? 2 : 0),
	/* Opt out of parallel tests on CI. */
	workers: isCI ? 1 : undefined,
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: isCI ? [['list'], ['github']] : 'list',
	/* Keep CI stable but allow fast-fail debugging locally via PLAYWRIGHT_FAST_FAIL=1. */
	timeout: isDevelopmentFastFail ? 30000 : 60000,

	/* Shared settings for all the projects below. */
	use: {
		/* Base URL to use in actions like `await page.goto('./apps/libresign')`. */
		baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'https://localhost',

		/* Force E2E execution in English regardless of container/user locale. */
		locale: 'en-US',
		extraHTTPHeaders: {
			'Accept-Language': 'en-US,en;q=0.9',
		},

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
