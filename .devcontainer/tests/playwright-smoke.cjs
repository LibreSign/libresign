/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const dns = require('node:dns').promises
const { chromium } = require('playwright')

const sleep = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds))

async function main() {
	const baseUrl = process.env.PLAYWRIGHT_BASE_URL
	if (!baseUrl) {
		throw new Error('PLAYWRIGHT_BASE_URL is required')
	}

	const target = new URL('/status.php', baseUrl)
	const addresses = await dns.lookup(target.hostname, { all: true })
	console.log('Resolved', target.hostname, addresses)

	const browser = await chromium.launch()
	try {
		const context = await browser.newContext({ ignoreHTTPSErrors: true })
		const page = await context.newPage()
		let lastError

		for (let attempt = 1; attempt <= 20; attempt++) {
			try {
				const response = await page.goto(target.href, {
					waitUntil: 'domcontentloaded',
					timeout: 5000,
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

				console.log('Playwright reached', response.url(), status)
				return
			} catch (error) {
				lastError = error
				if (attempt === 20) {
					break
				}
				await sleep(500)
			}
		}

		throw lastError
	} finally {
		await browser.close()
	}
}

main().catch((error) => {
	console.error(error)
	process.exit(1)
})
