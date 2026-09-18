/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'


type LayoutMetrics = {
	viewportHeight: number
	htmlHasExternalClass: boolean
	bodyHasExternalClass: boolean
	content: {
		top: number
		bottom: number
		height: number
		marginTop: string
		paddingBottom: string
	}
	container: {
		top: number
		bottom: number
		height: number
	}
}

/**
 * Read the geometry that must remain independent from Nextcloud's
 * authenticated/public page layout.
 *
 * @param page Playwright page
 */
async function getExternalLayoutMetrics(page: Page): Promise<LayoutMetrics> {
	return page.evaluate(() => {
		const content = document.querySelector<HTMLElement>('#content')
		const container = document.querySelector<HTMLElement>('.container')

		if (!content || !container) {
			throw new Error('External validation layout elements were not found')
		}

		const contentRect = content.getBoundingClientRect()
		const containerRect = container.getBoundingClientRect()
		const contentStyle = getComputedStyle(content)

		return {
			viewportHeight: window.innerHeight,
			htmlHasExternalClass: document.documentElement.classList.contains('libresign-external-page'),
			bodyHasExternalClass: document.body.classList.contains('libresign-external-page'),
			content: {
				top: contentRect.top,
				bottom: contentRect.bottom,
				height: contentRect.height,
				marginTop: contentStyle.marginTop,
				paddingBottom: contentStyle.paddingBottom,
			},
			container: {
				top: containerRect.top,
				bottom: containerRect.bottom,
				height: containerRect.height,
			},
		}
	})
}

/**
 * Assert that the external page owns the whole viewport.
 *
 * @param metrics Measured page geometry
 */
function expectFullViewportLayout(metrics: LayoutMetrics): void {
	expect(metrics.htmlHasExternalClass).toBe(true)
	expect(metrics.bodyHasExternalClass).toBe(true)

	expect(metrics.content.top).toBe(0)
	expect(metrics.content.bottom).toBe(metrics.viewportHeight)
	expect(metrics.content.height).toBe(metrics.viewportHeight)
	expect(metrics.content.marginTop).toBe('0px')
	expect(metrics.content.paddingBottom).toBe('0px')

	expect(metrics.container.top).toBe(0)
	expect(metrics.container.bottom).toBe(metrics.viewportHeight)
	expect(metrics.container.height).toBe(metrics.viewportHeight)
}
test('external validation page fills the desktop viewport', async ({ page }) => {
	await page.setViewportSize({ width: 1440, height: 900 })
	await page.goto('./index.php/apps/libresign/p/validation')

	await expect(page.locator('#content')).toBeVisible()
	await expect(page.locator('.container')).toBeVisible()

	expectFullViewportLayout(await getExternalLayoutMetrics(page))
})

test('external validation page fills a small viewport', async ({ page }) => {
	await page.setViewportSize({ width: 390, height: 639 })
	await page.goto('./index.php/apps/libresign/p/validation')

	await expect(page.locator('#content')).toBeVisible()
	await expect(page.locator('.container')).toBeVisible()

	expectFullViewportLayout(await getExternalLayoutMetrics(page))
})


test('external page styles do not affect authenticated LibreSign pages', async ({ page }) => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

	const tokenResponse = await page.request.get('./csrftoken', {
		failOnStatusCode: true,
	})
	const { token: requesttoken } = await tokenResponse.json() as { token: string }
	const origin = tokenResponse.url().replace(/index\.php.*/, '')

	const loginResponse = await page.request.post('./login', {
		form: {
			user: adminUser,
			password: adminPassword,
			requesttoken,
		},
		headers: {
			Origin: origin,
		},
		maxRedirects: 0,
		failOnStatusCode: false,
	})

	expect(loginResponse.headers()['x-user-id']).toBe(adminUser)

	await page.goto('./apps/libresign/f/preferences')

	const content = page.locator('#content')
	await expect(content).toBeVisible()

	const layout = await content.evaluate((element) => {
		const rect = element.getBoundingClientRect()
		const style = getComputedStyle(element)

		return {
			top: rect.top,
			height: rect.height,
			viewportHeight: window.innerHeight,
			marginTop: style.marginTop,
			htmlHasExternalClass: document.documentElement.classList.contains('libresign-external-page'),
			bodyHasExternalClass: document.body.classList.contains('libresign-external-page'),
		}
	})

	expect(layout.htmlHasExternalClass).toBe(false)
	expect(layout.bodyHasExternalClass).toBe(false)

	// Authenticated pages must keep Nextcloud's normal page geometry.
	// The regression made #content start at 0 and fill the complete viewport.
	expect(layout.top).toBeGreaterThan(0)
	expect(layout.height).toBeLessThan(layout.viewportHeight)
	expect(layout.marginTop).not.toBe('0px')
})
