/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'

import { login } from '../support/nc-login'
import { configureOpenSsl, setSystemPolicy } from '../support/nc-provisioning'
import { ensureFilesHomeInitialized, uploadFileToFilesApp, waitForFilesAction } from '../support/nc-files'
import { getSmallValidPdfBuffer } from '../support/pdf-fixtures'
import { useRequestSignPolicyGuard } from '../support/system-policies'

useRequestSignPolicyGuard()

async function enableDirectSigning(page: import('@playwright/test').Page) {
	await login(page.request, 'admin', 'admin')
	await configureOpenSsl(page.request, 'LibreSign E2E', { C: 'BR', O: 'LibreSign' })
	await setSystemPolicy(page.request, 'identify_methods', JSON.stringify({
		factors: [{ name: 'account', enabled: true, requirement: 'required', signatureMethods: { clickToSign: { enabled: true } } }],
	}))
}

async function confirmSigningAndDownload(page: import('@playwright/test').Page, expectedFilename: string) {
	const dialog = page.getByRole('dialog', { name: 'Sign PDF now' })
	await expect(dialog).toBeVisible()
	await expect(dialog).toContainText('without visible signature fields')
	const responsePromise = page.waitForResponse(response => response.url().includes('/api/v1/sign/direct') && response.request().method() === 'POST')
	const downloadPromise = page.waitForEvent('download')
	await dialog.getByRole('button', { name: 'Sign now', exact: true }).click()
	const response = await responsePromise
	expect(response.status()).toBe(200)
	expect(response.headers()['content-type']).toContain('application/pdf')
	const download = await downloadPromise
	expect(download.suggestedFilename()).toBe(expectedFilename)
	await expect(dialog).toHaveCount(0)
}

test('Sign PDF now uploads once, signs and downloads without entering the request wizard', async ({ page }) => {
	await enableDirectSigning(page)
	await page.goto('./apps/libresign')
	const action = page.getByRole('button', { name: 'Sign PDF now' })
	await expect(action).toBeVisible()
	const chooserPromise = page.waitForEvent('filechooser')
	await action.click()
	const chooser = await chooserPromise
	await chooser.setFiles({
		name: 'direct-upload.pdf',
		mimeType: 'application/pdf',
		buffer: await getSmallValidPdfBuffer(),
	})
	await confirmSigningAndDownload(page, 'direct-upload.pdf')
	await expect(page.getByRole('button', { name: 'Request signatures' })).toHaveCount(0)
})

test('Nextcloud Files keeps Sign myself and offers a separate immediate signing path', async ({ page }) => {
	await enableDirectSigning(page)
	await ensureFilesHomeInitialized(page.request)
	const filename = `direct-files-${Date.now()}.pdf`
	await page.goto('./apps/files')
	await waitForFilesAction(page, 'open-in-libresign')
	await uploadFileToFilesApp(page, {
		name: filename,
		mimeType: 'application/pdf',
		buffer: await getSmallValidPdfBuffer(),
	})
	const table = page.getByRole('table', { name: /List of your files and folders/i })
	const checkbox = table.getByRole('checkbox', { name: `Toggle selection for file "${filename}"` })
	await expect(checkbox).toHaveCount(1, { timeout: 30_000 })
	await checkbox.locator('xpath=ancestor::tr[1]').click({ button: 'right' })
	const menu = page.getByRole('menu').last()
	await expect(menu.getByRole('menuitem', { name: 'Sign myself' })).toBeVisible()
	await menu.getByRole('menuitem', { name: 'Sign PDF now' }).click()
	await confirmSigningAndDownload(page, filename)
	await expect(page.getByRole('tab', { name: 'LibreSign' })).toHaveCount(0)
})
