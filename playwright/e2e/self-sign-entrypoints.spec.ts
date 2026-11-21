/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { ensureFilesHomeInitialized, uploadFileToFilesApp, waitForFilesAction } from '../support/nc-files'
import { configureOpenSsl, setSystemPolicy } from '../support/nc-provisioning'
import { getSmallValidPdfBuffer } from '../support/pdf-fixtures'
import { clickAddSigner } from '../support/request-signature'
import { useRequestSignPolicyGuard } from '../support/system-policies'

useRequestSignPolicyGuard()

// Local development can use an installed Chrome; CI uses Playwright's browser.
if (process.env.PLAYWRIGHT_CHROME_BIN) {
	test.use({ launchOptions: { executablePath: process.env.PLAYWRIGHT_CHROME_BIN } })
}

async function configureSelfSigning(page: import('@playwright/test').Page): Promise<void> {
	await login(page.request, process.env.NEXTCLOUD_ADMIN_USER ?? 'admin', process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin')
	await configureOpenSsl(page.request, 'LibreSign E2E', {
		C: 'BR',
		OU: ['Test'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})
	await setSystemPolicy(page.request, 'identify_methods', JSON.stringify({
		factors: [{ name: 'account', enabled: true, requirement: 'required', signatureMethods: { clickToSign: { enabled: true } } }],
	}))
}

test('select the current account without a prominent extra button', async ({ page }) => {
	await configureSelfSigning(page)
	await page.goto('./apps/libresign')
	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' })
		.fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	await page.getByRole('button', { name: 'Send' }).click()
	await clickAddSigner(page)

	const dialog = page.getByRole('dialog', { name: /Add new signer/i }).last()
	await expect(dialog.getByRole('button', { name: 'Include myself as signer' })).toHaveCount(0)
	const useMyAccount = dialog.getByRole('button', { name: 'Use my account' })
	const save = dialog.getByRole('button', { name: 'Save' })
	await expect(useMyAccount).toBeVisible()
	await expect(save).toBeDisabled()
	await useMyAccount.click()
	await expect(save).toBeEnabled()
	await expect(dialog.getByRole('textbox', { name: 'Signer name' })).toHaveValue('admin')
	await save.click()
	await expect(page.getByRole('link', { name: 'Edit signer admin' })).toBeVisible({ timeout: 20000 })
	await expect(page.getByRole('button', { name: 'Request signatures' })).toBeVisible()
})

test('prepare a ready-to-sign request from the Nextcloud Files PDF context menu', async ({ page }) => {
	await configureSelfSigning(page)
	await ensureFilesHomeInitialized(page.request)

	const name = `libresign-self-sign-${Date.now()}.pdf`
	await page.goto('./apps/files')
	await waitForFilesAction(page, 'open-in-libresign')
	await uploadFileToFilesApp(page, {
		name,
		mimeType: 'application/pdf',
		buffer: await getSmallValidPdfBuffer(),
	})

	const table = page.getByRole('table', { name: /List of your files and folders/i })
	const checkbox = table.getByRole('checkbox', { name: `Toggle selection for file "${name}"` })
	await expect(checkbox).toHaveCount(1, { timeout: 30000 })
	await checkbox.locator('xpath=ancestor::tr[1]').click({ button: 'right' })

	const menu = page.getByRole('menu').last()
	const self = menu.getByRole('menuitem', { name: 'Sign myself' })
	await expect(self).toBeVisible()
	await self.click()

	await expect(page.getByRole('tab', { name: 'LibreSign' })).toHaveAttribute('aria-selected', 'true', { timeout: 15000 })
	await expect(page.getByRole('link', { name: 'Edit signer admin' })).toBeVisible({ timeout: 20000 })
	const sign = page.getByRole('button', { name: 'Sign document' })
	await expect(sign).toBeVisible({ timeout: 25000 })
	await expect(page.getByRole('button', { name: 'Request signatures' })).toHaveCount(0)
	await sign.click()
	await expect(page.getByRole('dialog')).toBeVisible({ timeout: 20000 })
})

test('Sign myself in LibreSign Files opens a ready-to-sign sidebar', async ({ page }) => {
	await configureSelfSigning(page)
	await page.goto('./apps/libresign')
	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' })
		.fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	await page.getByRole('button', { name: 'Send' }).click()
	await page.goto('./apps/libresign/f/filelist/sign')
	const row = page.locator('[data-cy-files-list-tbody] tr.files-list__row')
		.filter({ hasText: 'small_valid' }).first()
	await expect(row).toBeVisible({ timeout: 25000 })
	await row.click({ button: 'right' })
	await page.getByText('Sign myself', { exact: true }).last().click()
	await expect(page.getByRole('link', { name: 'Edit signer admin' })).toBeVisible({ timeout: 25000 })
	const sign = page.getByRole('button', { name: 'Sign document' })
	await expect(sign).toBeVisible({ timeout: 25000 })
	await expect(page.getByRole('button', { name: 'Request signatures' })).toHaveCount(0)
	await sign.click()
	await expect(page).toHaveURL(/\/apps\/libresign\/f\/sign\/[^/]+\/pdf/, { timeout: 25000 })
})
