/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const { chromium } = require('playwright')

async function main() {
	const target = process.env.PLAYWRIGHT_SMOKE_URL || 'http://nginx/status.php'
	const browser = await chromium.launch()

	try {
		const context = await browser.newContext({ ignoreHTTPSErrors: true })
		const page = await context.newPage()
		const response = await page.goto(target, {
			waitUntil: 'domcontentloaded',
			timeout: 10000,
		})

		if (!response) {
			throw new Error('Playwright did not receive an HTTP response')
		}
		if (response.status() !== 200) {
			throw new Error(`Expected HTTP 200, got ${response.status()}`)
		}

		const status = await response.json()
		if (status.installed !== true) {
			throw new Error('Nextcloud status endpoint does not report installed=true')
		}

		console.log('Playwright browser smoke passed:', response.url())
	} finally {
		await browser.close()
	}
}

main().catch((error) => {
	console.error(error)
	process.exit(1)
})
