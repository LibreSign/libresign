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
	const reset = await page.request.put('./ocs/v2.php/apps/libresign/api/v1/account/config/skip_direct_sign_confirmation', {
		data: { value: false },
		headers: { 'OCS-APIRequest': 'true' },
	})
	expect(reset.ok()).toBe(true)
}

async function confirmSigningAndDownload(page: import('@playwright/test').Page, filename: string, remember = false) {
	const dialog = page.getByRole('dialog', { name: 'Sign PDF now' })
	await expect(dialog).toBeVisible()
	await expect(dialog).toContainText('No visible signature field will be added.')
	if (remember) {
		await dialog.getByText('Do not show this confirmation again', { exact: true }).click()
		await expect(dialog.getByRole('checkbox', { name: 'Do not show this confirmation again' })).toBeChecked()
	}
	const responsePromise = page.waitForResponse(response => response.url().includes('/api/v1/sign/direct') && response.request().method() === 'POST')
	const downloadPromise = page.waitForEvent('download')
	await dialog.getByRole('button', { name: 'Sign now', exact: true }).click()
	const response = await responsePromise
	expect(response.status()).toBe(200)
	expect(response.headers()['content-type']).toContain('application/pdf')
	expect((await downloadPromise).suggestedFilename()).toBe(filename)
	await expect(dialog).toHaveCount(0)
}

async function pickPdfFromRequestMenu(page: import('@playwright/test').Page, filename: string) {
	const menu = page.locator('.files-list__header').getByRole('button', { name: 'Request', exact: true })
	await menu.click()
	const chooserPromise = page.waitForEvent('filechooser')
	await page.getByText('Sign PDF now', { exact: true }).last().click()
	await (await chooserPromise).setFiles({
		name: filename,
		mimeType: 'application/pdf',
		buffer: await getSmallValidPdfBuffer(),
	})
}

test('LibreSign keeps its request screen uncluttered and signs from the Files Request menu', async ({ page }) => {
	await enableDirectSigning(page)
	await page.goto('./apps/libresign/f/request')
	await expect(page.getByRole('button', { name: 'Sign PDF now' })).toHaveCount(0)
	await page.goto('./apps/libresign/f/filelist/sign')
	await pickPdfFromRequestMenu(page, 'direct-upload.pdf')
	await confirmSigningAndDownload(page, 'direct-upload.pdf', true)

	await page.goto('./apps/libresign/f/preferences')
	const confirmationSwitch = page.getByRole('switch', { name: 'Confirm before signing a PDF immediately' })
	await expect(confirmationSwitch).not.toBeChecked()
	await page.goto('./apps/libresign/f/filelist/sign')
	const downloadPromise = page.waitForEvent('download')
	await pickPdfFromRequestMenu(page, 'another-direct.pdf')
	expect((await downloadPromise).suggestedFilename()).toBe('another-direct.pdf')
	await expect(page.getByRole('dialog', { name: 'Sign PDF now' })).toHaveCount(0)

	await page.goto('./apps/libresign/f/preferences')
	const restoreResponse = page.waitForResponse(response => response.url().includes('/account/config/skip_direct_sign_confirmation')
		&& response.request().method() === 'PUT')
	await page.getByText('Confirm before signing a PDF immediately', { exact: true }).click()
	expect((await restoreResponse).status()).toBe(200)
	await expect(confirmationSwitch).toBeChecked()
	await page.goto('./apps/libresign/f/filelist/sign')
	await pickPdfFromRequestMenu(page, 'confirmation-restored.pdf')
	await expect(page.getByRole('dialog', { name: 'Sign PDF now' })).toBeVisible()
	await page.getByRole('dialog', { name: 'Sign PDF now' }).getByRole('button', { name: 'Cancel' }).click()
})

test('LibreSign offers PDF preparation instead of direct signing without calling the endpoint', async ({ page }) => {
	await enableDirectSigning(page)
	await page.goto('./apps/libresign/f/filelist/sign')
	const directCalls: string[] = []
	page.on('request', request => {
		if (request.url().includes('/api/v1/sign/direct')) {
			directCalls.push(request.url())
		}
	})
	await pickPdfFromRequestMenu(page, `prepare-${Date.now()}.pdf`)
	const dialog = page.getByRole('dialog', { name: 'Sign PDF now' })
	await expect(dialog.getByText('Need to place a visible signature or invite someone else?', { exact: false })).toBeVisible()
	await dialog.getByRole('button', { name: 'Prepare signing' }).click()
	await expect(dialog).toHaveCount(0)
	await expect(page.getByRole('button', { name: 'Sign document' })).toBeVisible()
	expect(directCalls).toEqual([])
})

test('Nextcloud Files uses two context actions and puts immediate signing inside the sidebar', async ({ page }) => {
	await enableDirectSigning(page)
	await ensureFilesHomeInitialized(page.request)
	const filename = `direct-files-${Date.now()}.pdf`
	await page.goto('./apps/files')
	await waitForFilesAction(page, 'open-in-libresign')
	await uploadFileToFilesApp(page, { name: filename, mimeType: 'application/pdf', buffer: await getSmallValidPdfBuffer() })
	const table = page.getByRole('table', { name: /List of your files and folders/i })
	const checkbox = table.getByRole('checkbox', { name: `Toggle selection for file "${filename}"` })
	await expect(checkbox).toHaveCount(1, { timeout: 30_000 })
	await checkbox.locator('xpath=ancestor::tr[1]').click({ button: 'right' })
	const menu = page.getByRole('menu').last()
	await expect(menu.getByRole('menuitem', { name: 'Sign myself' })).toBeVisible()
	await expect(menu.getByRole('menuitem', { name: 'Sign PDF now' })).toHaveCount(0)
	await menu.getByRole('menuitem', { name: 'Open in LibreSign' }).click()
	const sidebar = page.locator('#request-signature-tab')
	const immediateAction = page.getByRole('button', { name: 'Sign PDF now' })
	await expect(sidebar).toBeVisible()
	await expect(immediateAction).toBeVisible()
	await immediateAction.click()
	await confirmSigningAndDownload(page, filename)
})

test('Nextcloud Files can prepare signing from the direct-sign dialog without invoking the direct endpoint', async ({ page }) => {
	await enableDirectSigning(page)
	await ensureFilesHomeInitialized(page.request)
	const filename = `prepare-files-${Date.now()}.pdf`
	await page.goto('./apps/files')
	await waitForFilesAction(page, 'open-in-libresign')
	await uploadFileToFilesApp(page, { name: filename, mimeType: 'application/pdf', buffer: await getSmallValidPdfBuffer() })
	const table = page.getByRole('table', { name: /List of your files and folders/i })
	const checkbox = table.getByRole('checkbox', { name: `Toggle selection for file "${filename}"` })
	await expect(checkbox).toHaveCount(1, { timeout: 30_000 })
	await checkbox.locator('xpath=ancestor::tr[1]').click({ button: 'right' })
	await page.getByRole('menu').last().getByRole('menuitem', { name: 'Open in LibreSign' }).click()
	await expect(page.getByRole('button', { name: 'Sign PDF now' })).toBeVisible()
	const directCalls: string[] = []
	page.on('request', request => {
		if (request.url().includes('/api/v1/sign/direct')) {
			directCalls.push(request.url())
		}
	})
	await page.getByRole('button', { name: 'Sign PDF now' }).click()
	await page.getByRole('dialog', { name: 'Sign PDF now' }).getByRole('button', { name: 'Prepare signing' }).click()
	await expect(page.getByRole('button', { name: 'Sign document' })).toBeVisible()
	await expect(page.getByRole('button', { name: 'Sign PDF now' })).toHaveCount(0)
	expect(directCalls).toEqual([])
})
