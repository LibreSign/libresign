/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test, type Page } from '@playwright/test'

import { login } from '../support/nc-login'
import { setUserLanguage } from '../support/nc-provisioning'

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

function collectJavascriptErrors(page: Page) {
	const issues: string[] = []

	page.on('console', (message) => {
		if (message.type() !== 'error') {
			return
		}

		const text = message.text().trim()
		if (!text) {
			return
		}

		if (text.includes('/img/app-dark.svg') && text.includes('Content Security Policy directive')) {
			return
		}

		if (text.includes('/core/img/actions/error.svg') && text.includes('Content Security Policy directive')) {
			return
		}

		if (text.startsWith('Failed to load resource:')) {
			return
		}

		issues.push(`console.error: ${text}`)
	})

	page.on('pageerror', (error) => {
		const message = error.message.trim()
		issues.push(`pageerror: ${message}`)
	})

	return {
		clear() {
			issues.length = 0
		},
		all() {
			return [...issues]
		},
	}
}

async function getCatalogCollapseButton(page: Page) {
	return page.getByRole('button', {
		name: /Collapse settings categories|Expand settings categories/i,
	}).first()
}

async function getCatalogViewButton(page: Page) {
	return page.getByRole('button', {
		name: /Switch to compact view|Switch to card view/i,
	}).first()
}

async function waitForUserConfigSave(page: Page, key: string) {
	return page.waitForResponse((response) => {
		return response.request().method() === 'PUT'
			&& response.url().includes(`/apps/libresign/api/v1/account/config/${key}`)
			&& response.ok()
	})
}

async function getPrimaryScrollTop(page: Page) {
	return page.evaluate(() => {
		const appContent = document.querySelector('#app-content')
		if (appContent instanceof HTMLElement && appContent.scrollHeight > (appContent.clientHeight + 1)) {
			return appContent.scrollTop
		}

		const toolbar = document.querySelector('.policy-workbench__catalog-search')
		let current = toolbar instanceof HTMLElement ? toolbar.parentElement : null
		while (current) {
			const styles = window.getComputedStyle(current)
			const isScrollable = (styles.overflowY === 'auto' || styles.overflowY === 'scroll')
				&& current.scrollHeight > (current.clientHeight + 1)
			if (isScrollable) {
				return current.scrollTop
			}

			current = current.parentElement
		}

		const root = document.scrollingElement as HTMLElement | null
		return window.scrollY || root?.scrollTop || 0
	})
}

async function scrollAppContentToRatio(page: Page, ratio: number) {
	const getMaxScrollable = async () => {
		return page.evaluate(() => {
			const appContent = document.querySelector('#app-content')
			if (appContent instanceof HTMLElement && appContent.scrollHeight > (appContent.clientHeight + 1)) {
				return Math.max(0, appContent.scrollHeight - appContent.clientHeight)
			}

			const toolbar = document.querySelector('.policy-workbench__catalog-search')
			let current = toolbar instanceof HTMLElement ? toolbar.parentElement : null
			while (current) {
				const styles = window.getComputedStyle(current)
				const isScrollable = (styles.overflowY === 'auto' || styles.overflowY === 'scroll')
					&& current.scrollHeight > (current.clientHeight + 1)
				if (isScrollable) {
					return Math.max(0, current.scrollHeight - current.clientHeight)
				}

				current = current.parentElement
			}

			const root = document.scrollingElement as HTMLElement | null
			const rootScrollHeight = root?.scrollHeight ?? document.documentElement.scrollHeight
			return Math.max(0, rootScrollHeight - window.innerHeight)
		})
	}

	// Wait for page content to be rendered and scrollable, with longer timeout for slow layouts
	await expect.poll(getMaxScrollable, { timeout: 20000, intervals: [500] }).toBeGreaterThan(400).catch(() => {
		// Fallback: if content doesn't reach 400px, continue with available scroll space
		return getMaxScrollable()
	})

	const scrollTarget = await page.evaluate((value) => {
		const appContent = document.querySelector('#app-content')
		if (appContent instanceof HTMLElement && appContent.scrollHeight > (appContent.clientHeight + 1)) {
			const maxScroll = Math.max(0, appContent.scrollHeight - appContent.clientHeight)
			const target = Math.round(maxScroll * value)
			appContent.scrollTo({ top: target, behavior: 'auto' })
			appContent.dispatchEvent(new Event('scroll'))
			return target
		}

		const toolbar = document.querySelector('.policy-workbench__catalog-search')
		let current = toolbar instanceof HTMLElement ? toolbar.parentElement : null
		while (current) {
			const styles = window.getComputedStyle(current)
			const isScrollable = (styles.overflowY === 'auto' || styles.overflowY === 'scroll')
				&& current.scrollHeight > (current.clientHeight + 1)
			if (isScrollable) {
				const maxScroll = Math.max(0, current.scrollHeight - current.clientHeight)
				const target = Math.round(maxScroll * value)
				current.scrollTo({ top: target, behavior: 'auto' })
				current.dispatchEvent(new Event('scroll'))
				return target
			}

			current = current.parentElement
		}

		const root = document.scrollingElement as HTMLElement | null
		const rootScrollHeight = root?.scrollHeight ?? document.documentElement.scrollHeight
		const maxScroll = Math.max(0, rootScrollHeight - window.innerHeight)
		const target = Math.round(maxScroll * value)
		window.scrollTo({ top: target, behavior: 'auto' })
		window.dispatchEvent(new Event('scroll'))
		return target
	}, ratio)

	return { scrollTarget }
}

test('catalog controls keep behavior, layout, and JS health', async ({ page }) => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

	await login(page.request, adminUser, adminPassword)
	await setUserLanguage(page.request, adminUser, 'en')

	await page.setViewportSize({ width: 1365, height: 950 })

	const errorCollector = collectJavascriptErrors(page)

	await page.goto('./settings/admin/libresign')

	const searchField = page.getByRole('textbox', { name: /Search settings/i }).first()
	await expect(searchField).toBeVisible({ timeout: 20000 })

	const categoryToggles = page.locator('.policy-workbench__category-toggle')
	await expect(categoryToggles.first()).toBeVisible({ timeout: 20000 })
	await expect(categoryToggles).toHaveCount(7)

	const workbenchSection = page.locator('.policy-workbench__section').first()
	await expect(workbenchSection).toBeVisible({ timeout: 20000 })

	// Ignore potential startup noise and only validate errors introduced by user interactions.
	errorCollector.clear()

	const collapseButton = await getCatalogCollapseButton(page)
	const initialCollapseLabel = await collapseButton.getAttribute('aria-label')
	if (initialCollapseLabel && /Expand settings categories/i.test(initialCollapseLabel)) {
		await Promise.all([
			waitForUserConfigSave(page, 'policy_workbench_catalog_collapsed'),
			collapseButton.click(),
		])
		await expect(collapseButton).toHaveAttribute('aria-label', /Collapse settings categories/i)
	}

	await Promise.all([
		waitForUserConfigSave(page, 'policy_workbench_catalog_collapsed'),
		collapseButton.click(),
	])
	await expect(collapseButton).toHaveAttribute('aria-label', /Expand settings categories/i)

	await Promise.all([
		waitForUserConfigSave(page, 'policy_workbench_catalog_collapsed'),
		collapseButton.click(),
	])
	await expect(collapseButton).toHaveAttribute('aria-label', /Collapse settings categories/i)

	const viewButton = await getCatalogViewButton(page)
	const initialViewLabel = await viewButton.getAttribute('aria-label')
	if (initialViewLabel && /Switch to card view/i.test(initialViewLabel)) {
		await Promise.all([
			waitForUserConfigSave(page, 'policy_workbench_catalog_compact_view'),
			viewButton.click(),
		])
		await expect(viewButton).toHaveAttribute('aria-label', /Switch to compact view/i)
	}
	await expect(viewButton).toHaveAttribute('aria-label', /Switch to compact view/i)
	await expect(workbenchSection).toBeVisible()

	await Promise.all([
		waitForUserConfigSave(page, 'policy_workbench_catalog_compact_view'),
		viewButton.click(),
	])
	await expect(viewButton).toHaveAttribute('aria-label', /Switch to card view/i)
	await expect(workbenchSection).toBeVisible()

	await Promise.all([
		waitForUserConfigSave(page, 'policy_workbench_catalog_compact_view'),
		viewButton.click(),
	])
	await expect(viewButton).toHaveAttribute('aria-label', /Switch to compact view/i)

	expect(errorCollector.all(), 'No JavaScript errors should happen during collapse/expand and view switching').toEqual([])
})

test('chip navigation expands target category when catalog is collapsed', async ({ page }) => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

	await login(page.request, adminUser, adminPassword)
	await setUserLanguage(page.request, adminUser, 'en')

	await page.setViewportSize({ width: 1365, height: 950 })

	const errorCollector = collectJavascriptErrors(page)

	await page.goto('./settings/admin/libresign')

	const searchField = page.getByRole('textbox', { name: /Search settings/i }).first()
	await expect(searchField).toBeVisible({ timeout: 20000 })

	const collapseButton = await getCatalogCollapseButton(page)
	const initialCollapseLabel = await collapseButton.getAttribute('aria-label')
	if (initialCollapseLabel && /Expand settings categories/i.test(initialCollapseLabel)) {
		await Promise.all([
			waitForUserConfigSave(page, 'policy_workbench_catalog_collapsed'),
			collapseButton.click(),
		])
		await expect(collapseButton).toHaveAttribute('aria-label', /Collapse settings categories/i)
	}
	await Promise.all([
		waitForUserConfigSave(page, 'policy_workbench_catalog_collapsed'),
		collapseButton.click(),
	])
	await expect(collapseButton).toHaveAttribute('aria-label', /Expand settings categories/i)

	const targetSectionToggle = page.locator('#policy-category-how-signing-works .policy-workbench__category-toggle').first()
	const targetSection = page.locator('#policy-category-how-signing-works')
	const initialSectionY = await targetSection.boundingBox().then((box) => box?.y ?? Number.POSITIVE_INFINITY)
	await expect(targetSectionToggle).toHaveAttribute('aria-expanded', 'false')

	const targetChip = page.getByRole('button', { name: /Go to How signing works/i }).first()
	await expect(targetChip).toBeVisible({ timeout: 20000 })
	await targetChip.click()

	await expect(targetSectionToggle).toHaveAttribute('aria-expanded', 'true')

	await expect.poll(async () => {
		const box = await targetSection.boundingBox()
		return box?.y ?? Number.POSITIVE_INFINITY
	}, { timeout: 10000 }).toBeLessThan(initialSectionY)

	expect(errorCollector.all(), 'No JavaScript errors should happen during chip navigation').toEqual([])
})

test('catalog collapse and per-category expanded state persist after reload', async ({ page }) => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

	await login(page.request, adminUser, adminPassword)
	await setUserLanguage(page.request, adminUser, 'en')

	await page.setViewportSize({ width: 1365, height: 950 })

	const errorCollector = collectJavascriptErrors(page)

	await page.goto('./settings/admin/libresign')

	const searchField = page.getByRole('textbox', { name: /Search settings/i }).first()
	await expect(searchField).toBeVisible({ timeout: 20000 })

	const collapseButton = await getCatalogCollapseButton(page)
	const initialCollapseLabel = await collapseButton.getAttribute('aria-label')
	if (initialCollapseLabel && /Expand settings categories/i.test(initialCollapseLabel)) {
		await Promise.all([
			waitForUserConfigSave(page, 'policy_workbench_catalog_collapsed'),
			collapseButton.click(),
		])
		await expect(collapseButton).toHaveAttribute('aria-label', /Collapse settings categories/i)
	}

	await Promise.all([
		waitForUserConfigSave(page, 'policy_workbench_catalog_collapsed'),
		collapseButton.click(),
	])
	await expect(collapseButton).toHaveAttribute('aria-label', /Expand settings categories/i)

	await page.reload()
	await expect(searchField).toBeVisible({ timeout: 20000 })
	await expect(collapseButton).toHaveAttribute('aria-label', /Expand settings categories/i)

	const signingWorksToggle = page.locator('#policy-category-how-signing-works .policy-workbench__category-toggle').first()
	await expect(signingWorksToggle).toHaveAttribute('aria-expanded', 'false')

	const signerSeesToggle = page.locator('#policy-category-signer-experience .policy-workbench__category-toggle').first()
	await expect(signerSeesToggle).toHaveAttribute('aria-expanded', 'false')

	await Promise.all([
		waitForUserConfigSave(page, 'policy_workbench_category_collapsed_state'),
		signingWorksToggle.click(),
	])
	await expect(signingWorksToggle).toHaveAttribute('aria-expanded', 'true')
	await expect(collapseButton).toHaveAttribute('aria-label', /Collapse settings categories/i)

	await page.reload()
	await expect(searchField).toBeVisible({ timeout: 20000 })
	await expect(collapseButton).toHaveAttribute('aria-label', /Collapse settings categories/i)
	await expect(signingWorksToggle).toHaveAttribute('aria-expanded', 'true')
	await expect(signerSeesToggle).toHaveAttribute('aria-expanded', 'false')

	expect(errorCollector.all(), 'No JavaScript errors should happen while persisting catalog state').toEqual([])
})

test('search temporarily expands result sections without persisting section state', async ({ page }) => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

	await login(page.request, adminUser, adminPassword)
	await setUserLanguage(page.request, adminUser, 'en')

	await page.setViewportSize({ width: 1365, height: 950 })

	const errorCollector = collectJavascriptErrors(page)

	await page.goto('./settings/admin/libresign')

	const searchField = page.getByRole('textbox', { name: /Search settings/i }).first()
	await expect(searchField).toBeVisible({ timeout: 20000 })

	const collapseButton = await getCatalogCollapseButton(page)
	if (/Collapse settings categories/i.test((await collapseButton.getAttribute('aria-label')) ?? '')) {
		await Promise.all([
			waitForUserConfigSave(page, 'policy_workbench_catalog_collapsed'),
			collapseButton.click(),
		])
	}
	await expect(collapseButton).toHaveAttribute('aria-label', /Expand settings categories/i)

	const collapsedStateSaves: string[] = []
	page.on('request', (request) => {
		if (request.method() === 'PUT' && request.url().includes('/apps/libresign/api/v1/account/config/policy_workbench_category_collapsed_state')) {
			collapsedStateSaves.push(request.url())
		}
	})

	await searchField.fill('signing')

	const signingWorksToggle = page.locator('#policy-category-how-signing-works .policy-workbench__category-toggle').first()
	await expect(signingWorksToggle).toBeVisible({ timeout: 10000 })
	await expect(signingWorksToggle).toHaveAttribute('aria-expanded', 'true')

	await page.waitForTimeout(400)
	expect(collapsedStateSaves, 'Search-driven expansion must not persist section collapsed state').toHaveLength(0)

	await searchField.fill('')
	await expect(signingWorksToggle).toHaveAttribute('aria-expanded', 'false')

	expect(errorCollector.all(), 'No JavaScript errors should happen while showing filtered results').toEqual([])
})

test('back to top returns to search toolbar instead of absolute page top', async ({ page }) => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

	await login(page.request, adminUser, adminPassword)
	await setUserLanguage(page.request, adminUser, 'en')

	await page.setViewportSize({ width: 1365, height: 950 })

	const errorCollector = collectJavascriptErrors(page)

	await page.goto('./settings/admin/libresign')

	const searchField = page.getByRole('textbox', { name: /Search settings/i }).first()
	await expect(searchField).toBeVisible({ timeout: 20000 })

	const collapseButton = await getCatalogCollapseButton(page)
	if (/Expand settings categories/i.test((await collapseButton.getAttribute('aria-label')) ?? '')) {
		await Promise.all([
			waitForUserConfigSave(page, 'policy_workbench_catalog_collapsed'),
			collapseButton.click(),
		])
	}

	const viewButton = await getCatalogViewButton(page)
	if (/Switch to card view/i.test((await viewButton.getAttribute('aria-label')) ?? '')) {
		await Promise.all([
			waitForUserConfigSave(page, 'policy_workbench_catalog_compact_view'),
			viewButton.click(),
		])
	}

	const { scrollTarget } = await scrollAppContentToRatio(page, 0.75)
	const minExpectedScroll = Math.max(40, Math.floor(scrollTarget * 0.5))

	await expect.poll(async () => {
		return getPrimaryScrollTop(page)
	}, { timeout: 10000 }).toBeGreaterThan(minExpectedScroll)

	const backToTopButton = page.locator('.policy-workbench__back-to-top').first()
	await expect(backToTopButton).toBeVisible({ timeout: 10000 })
	await backToTopButton.click()

	await expect(searchField).toBeFocused({ timeout: 10000 })

	const afterScroll = await page.evaluate(() => {
		const toolbar = document.querySelector('.policy-workbench__catalog-search') as HTMLElement | null
		return {
			toolbarTop: toolbar?.getBoundingClientRect().top ?? Number.POSITIVE_INFINITY,
		}
	})
	const containerScrollTop = await getPrimaryScrollTop(page)

	expect(containerScrollTop, 'Back-to-top should not jump to absolute page top').toBeGreaterThan(100)
	expect(afterScroll.toolbarTop, 'Search toolbar should be brought near the top of the viewport').toBeLessThan(250)

	expect(errorCollector.all(), 'No JavaScript errors should happen while using back-to-top').toEqual([])
})

test('active category chip tracks the section with visible cards while scrolling', async ({ page }) => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

	await login(page.request, adminUser, adminPassword)
	await setUserLanguage(page.request, adminUser, 'en')

	await page.setViewportSize({ width: 1365, height: 950 })

	const errorCollector = collectJavascriptErrors(page)

	await page.goto('./settings/admin/libresign')

	const searchField = page.getByRole('textbox', { name: /Search settings/i }).first()
	await expect(searchField).toBeVisible({ timeout: 20000 })

	const collapseButton = await getCatalogCollapseButton(page)
	if (/Expand settings categories/i.test((await collapseButton.getAttribute('aria-label')) ?? '')) {
		await Promise.all([
			waitForUserConfigSave(page, 'policy_workbench_catalog_collapsed'),
			collapseButton.click(),
		])
	}

	const viewButton = await getCatalogViewButton(page)
	if (/Switch to card view/i.test((await viewButton.getAttribute('aria-label')) ?? '')) {
		await Promise.all([
			waitForUserConfigSave(page, 'policy_workbench_catalog_compact_view'),
			viewButton.click(),
		])
	}

	await scrollAppContentToRatio(page, 0.75)

	await expect.poll(async () => {
		return getPrimaryScrollTop(page)
	}, { timeout: 10000 }).toBeGreaterThan(400)

	await expect.poll(async () => {
		return page.evaluate(() => {
			const stickyNav = document.querySelector('.policy-workbench__category-nav-sticky') as HTMLElement | null
			const appContent = document.querySelector('#app-content') as HTMLElement | null
			const topCutoff = (stickyNav?.getBoundingClientRect().bottom ?? 140) + 12
			const bottomCutoff = appContent?.getBoundingClientRect().bottom ?? window.innerHeight

			const sectionWithVisibleCard = Array.from(document.querySelectorAll('.policy-workbench__category-section')).find((section) => {
				const cards = Array.from(section.querySelectorAll<HTMLElement>('.policy-workbench__setting-tile, .policy-workbench__settings-row'))
				return cards.some((card) => {
					const rect = card.getBoundingClientRect()
					return rect.top >= topCutoff && rect.bottom <= bottomCutoff && rect.bottom > rect.top
				})
			}) as HTMLElement | undefined

			const expectedSectionTitle = sectionWithVisibleCard?.querySelector('.policy-workbench__category-title')?.textContent?.trim() ?? null
			const activeChipLabel = document.querySelector('.policy-workbench__category-chip--active')?.textContent?.trim() ?? null

			return {
				expectedSectionTitle,
				activeChipLabel,
				hasFullyVisibleCard: Boolean(expectedSectionTitle),
				isSynced: Boolean(expectedSectionTitle && activeChipLabel && expectedSectionTitle === activeChipLabel),
			}
		})
	}, { timeout: 10000 }).toMatchObject({ hasFullyVisibleCard: true, isSynced: true })

	const syncResult = await page.evaluate(() => {
		const stickyNav = document.querySelector('.policy-workbench__category-nav-sticky') as HTMLElement | null
		const appContent = document.querySelector('#app-content') as HTMLElement | null
		const topCutoff = (stickyNav?.getBoundingClientRect().bottom ?? 140) + 12
		const bottomCutoff = appContent?.getBoundingClientRect().bottom ?? window.innerHeight

		const sectionWithVisibleCard = Array.from(document.querySelectorAll('.policy-workbench__category-section')).find((section) => {
			const cards = Array.from(section.querySelectorAll<HTMLElement>('.policy-workbench__setting-tile, .policy-workbench__settings-row'))
			return cards.some((card) => {
				const rect = card.getBoundingClientRect()
				return rect.top >= topCutoff && rect.bottom <= bottomCutoff && rect.bottom > rect.top
			})
		}) as HTMLElement | undefined

		const expectedSectionTitle = sectionWithVisibleCard?.querySelector('.policy-workbench__category-title')?.textContent?.trim() ?? null
		const activeChipLabel = document.querySelector('.policy-workbench__category-chip--active')?.textContent?.trim() ?? null

		return {
			expectedSectionTitle,
			activeChipLabel,
		}
	})

	expect(syncResult.expectedSectionTitle).not.toBeNull()
	expect(syncResult.activeChipLabel).toBe(syncResult.expectedSectionTitle)

	expect(errorCollector.all(), 'No JavaScript errors should happen while syncing active chip on scroll').toEqual([])
})
