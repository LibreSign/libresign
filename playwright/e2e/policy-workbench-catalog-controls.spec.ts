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
