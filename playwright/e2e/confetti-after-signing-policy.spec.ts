/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test, type Page } from '@playwright/test'

import { login } from '../support/nc-login'
import { configureOpenSsl, deleteAppConfig, setAppConfig, setCertificateEngine, setSystemPolicy } from '../support/nc-provisioning'

async function sortByCreatedAtDescending(page: Page) {
	const createdAtTh = page.getByRole('columnheader', { name: 'Created at' })
	const sortDirection = await createdAtTh.evaluate((element: HTMLElement) => element.ariaSort)
	if (sortDirection !== 'descending') {
		await page.getByRole('button', { name: 'Created at' }).click()
		if (sortDirection === 'none') {
			await page.getByRole('button', { name: 'Created at' }).click()
		}
	}
}

async function runSelfSigningFlow(page: Page): Promise<void> {
	await page.goto('./apps/libresign')
	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	await page.getByRole('button', { name: 'Send' }).click()
	await page.getByRole('button', { name: 'Add signer' }).click()
	await page.getByPlaceholder('Account').fill('admin')
	await page.getByText('admin@email.tld').click()
	await page.getByRole('button', { name: 'Save' }).click()
	await page.getByRole('button', { name: 'Request signatures' }).click()
	await page.getByRole('button', { name: 'Send' }).click()

	await page.locator('#fileslist').getByRole('link', { name: 'Files' }).click()
	await sortByCreatedAtDescending(page)

	const uniqueName = `confetti-policy-${Date.now()}.pdf`
	const firstRow = page.locator('[data-cy-files-list-tbody] tr.files-list__row')
		.filter({ hasText: 'small_valid' })
		.first()
	await firstRow.getByRole('button', { name: 'Actions' }).click()
	await page.getByRole('menuitem', { name: 'Rename' }).click()
	const fileNameInput = page.getByLabel('File name')
	await fileNameInput.fill(uniqueName)
	await fileNameInput.press('Enter')
	await expect(fileNameInput).toBeHidden({ timeout: 10000 })

	const filesSearch = page.getByRole('searchbox', { name: /Search here/i }).first()
	if (await filesSearch.isVisible({ timeout: 2000 }).catch(() => false)) {
		await filesSearch.fill(uniqueName)
	}

	const targetRow = page.locator('[data-cy-files-list-tbody] tr.files-list__row').filter({ hasText: uniqueName })
	await expect(targetRow).toBeVisible({ timeout: 20000 })
	await targetRow.getByRole('button', { name: 'Actions' }).click()
	await page.getByRole('menuitem', { name: 'Sign' }).click()

	await page.waitForURL('**/f/sign/**/pdf')
	const signButton = page.getByRole('button', { name: 'Sign the document.' })
	await expect(signButton).toBeVisible()
	await signButton.click()

	const signResponsePromise = page.waitForResponse((response) =>
		response.request().method() === 'POST'
		&& response.url().includes('/apps/libresign/api/v1/sign/'),
	)
	await page.getByRole('button', { name: 'Sign document' }).click()
	const signResponse = await signResponsePromise
	const signResponseBody = await signResponse.text()
	if (!signResponse.ok()) {
		throw new Error(`Sign API failed with status ${signResponse.status()}: ${signResponseBody}`)
	}

	await expect(page.getByText('This document is valid')).toBeVisible()
}

function confettiCanvasLocator(page: Page) {
	return page.locator('canvas[style*="position: fixed"][style*="z-index: 1000"][style*="pointer-events: none"]')
}

test.describe.configure({ mode: 'serial', retries: 0, timeout: 120000 })

test.beforeEach(async ({ page }) => {
	await login(
		page.request,
		process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
		process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
	)

	await configureOpenSsl(page.request, 'LibreSign Test', {
		C: 'BR',
		OU: ['Organization Unit'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})

	await setCertificateEngine(page.request, 'openssl')
	await setAppConfig(page.request, 'libresign', 'signature_engine', 'PhpNative')
	await deleteAppConfig(page.request, 'libresign', 'tsa_url')
	await setSystemPolicy(
		page.request,
		'identify_methods',
		JSON.stringify([
			{ name: 'account', enabled: true, mandatory: true, signatureMethods: { clickToSign: { enabled: true } } },
			{ name: 'email', enabled: false, mandatory: false },
		]),
	)
})

test('shows confetti after signing when policy is enabled', async ({ page }) => {
	await setSystemPolicy(page.request, 'show_confetti_after_signing', JSON.stringify(true))

	await runSelfSigningFlow(page)

	await expect.poll(async () => confettiCanvasLocator(page).count(), { timeout: 10000 }).toBeGreaterThan(0)
})

test('does not show confetti after signing when policy is disabled', async ({ page }) => {
	await setSystemPolicy(page.request, 'show_confetti_after_signing', JSON.stringify(false))

	await runSelfSigningFlow(page)

	await expect(confettiCanvasLocator(page)).toHaveCount(0)
})
