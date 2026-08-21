/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mkdir } from 'node:fs/promises'
import { resolve } from 'node:path'

import { expect, test, type APIRequestContext, type Locator, type Page } from '@playwright/test'

import { login } from '../support/nc-login'
import {
	createAuthenticatedRequestContext,
	getSystemPolicySnapshot,
	policyRequest,
	restoreSystemPolicySnapshot,
	type SystemPolicySnapshot,
} from '../support/policy-api'

const POLICY_KEY = 'identification_documents'
const SCREENSHOT_DIR = resolve(process.cwd(), 'build/playwright/visual-output')

test.describe.configure({ mode: 'serial', timeout: 180000 })

let adminContext: APIRequestContext
let originalPolicy: SystemPolicySnapshot

test.beforeEach(async () => {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
	adminContext = await createAuthenticatedRequestContext(adminUser, adminPassword)
	originalPolicy = await getSystemPolicySnapshot(adminContext, POLICY_KEY)

	const response = await policyRequest(
		adminContext,
		'POST',
		`/apps/libresign/api/v1/policies/system/${POLICY_KEY}`,
		{
			value: { enabled: true, approvers: [adminUser] },
			allowChildOverride: true,
		},
	)
	expect(response.httpStatus, response.message).toBe(200)
})

test.afterEach(async () => {
	try {
		if (adminContext && originalPolicy) {
			await restoreSystemPolicySnapshot(adminContext, POLICY_KEY, originalPolicy)
		}
	} finally {
		await adminContext?.dispose()
	}
})

async function shot(page: Page, name: string): Promise<void> {
	await mkdir(SCREENSHOT_DIR, { recursive: true })
	await page.screenshot({ path: resolve(SCREENSHOT_DIR, `${name}.png`), fullPage: true })
}

function emptyStatus(page: Page): Locator {
	return page.getByText(/Not sent yet/i)
}

function deleteButton(page: Page): Locator {
	return page.getByRole('button', { name: /Delete file/i })
}

function uploadButton(page: Page): Locator {
	return page.getByRole('button', { name: /Upload file/i })
}

async function waitForIdDocsCard(page: Page): Promise<void> {
	await expect(emptyStatus(page).or(deleteButton(page))).toBeVisible({ timeout: 20_000 })
}

async function clearExistingIdDocument(page: Page): Promise<void> {
	await waitForIdDocsCard(page)
	for (let attempt = 0; attempt < 5; attempt++) {
		if (!(await deleteButton(page).isVisible().catch(() => false))) {
			break
		}
		await deleteButton(page).click()
		await expect(page.getByText(/File was deleted\./i)).toBeVisible({ timeout: 20_000 })
		await waitForIdDocsCard(page)
	}
	await expect(emptyStatus(page)).toBeVisible({ timeout: 20_000 })
	await expect(uploadButton(page)).toBeVisible()
}

test('identification documents appear on the account page after upload', async ({ page }) => {
	test.slow()

	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	await page.goto('./apps/libresign')
	await expect(page.getByRole('button', { name: /Upload from URL/i })).toBeVisible({ timeout: 20_000 })
	await expect(page.getByRole('link', { name: /Documents Validation/i })).toBeVisible({ timeout: 20_000 })
	await shot(page, '01-libresign-home')

	await page.goto('./apps/libresign/f/account')
	await expect(page.getByRole('heading', { name: /Identification documents/i })).toBeVisible({ timeout: 20_000 })
	await clearExistingIdDocument(page)
	await shot(page, '02-account-id-docs-empty')

	const [fileChooser] = await Promise.all([
		page.waitForEvent('filechooser'),
		uploadButton(page).click(),
	])
	await fileChooser.setFiles(resolve(process.cwd(), 'tests/php/fixtures/pdfs/small_valid.pdf'))

	await expect(page.getByText(/File was sent\./i)).toBeVisible({ timeout: 20_000 })
	await expect(deleteButton(page)).toBeVisible({ timeout: 20_000 })
	await expect(emptyStatus(page)).toHaveCount(0)
	await shot(page, '03-account-id-doc-uploaded')

	await page.goto('./apps/libresign/f/docs/id-docs/validation')
	await expect(page.getByRole('columnheader', { name: /Owner/i })).toBeVisible({ timeout: 20_000 })
	await expect(page.getByRole('cell', { name: /^admin$/i }).first()).toBeVisible({ timeout: 20_000 })
	await expect(page.getByText(/waiting for approval/i).first()).toBeVisible()
	await expect(page.getByRole('button', { name: /Sign/i }).first()).toBeVisible()
	await shot(page, '04-id-docs-approval-list')

	await page.goto('./settings/admin/libresign')
	const catalogSearch = page.locator('.policy-workbench__catalog-search').getByRole('textbox').first()
	await expect(catalogSearch).toBeVisible({ timeout: 20_000 })

	const collapseButton = page.getByRole('button', {
		name: /Collapse settings categories|Expand settings categories/i,
	}).first()
	if (/Expand/i.test((await collapseButton.getAttribute('aria-label')) ?? '')) {
		await collapseButton.click()
	}

	await catalogSearch.fill('identifica')
	await expect(page.getByRole('button', {
		name: /Identification documents flow/i,
	}).first()).toBeVisible({ timeout: 20_000 })
	await shot(page, '05-settings-identification-documents')
})
