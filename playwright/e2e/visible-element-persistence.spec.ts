/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, setAppConfig } from '../support/nc-provisioning'

test('visible signature element persists and can be deleted', async ({ page }) => {
	const requestSignatureTab = page.locator('#request-signature-tab')
	const setupSignaturePositionsButton = requestSignatureTab.getByRole('button', { name: 'Setup signature positions' })
	const openSidebarButton = page.getByRole('button', { name: 'Open sidebar' })

	async function reopenFileFromUuid(uuid: string) {
		await page.goto(`./apps/libresign/f/filelist/sign?uuid=${uuid}`)
		if (await openSidebarButton.isVisible()) {
			await openSidebarButton.click()
		}
		await expect(setupSignaturePositionsButton).toBeVisible()
		await setupSignaturePositionsButton.click()
	}

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

	await setAppConfig(
		page.request,
		'libresign',
		'identify_methods',
		JSON.stringify([
			{ name: 'account', enabled: true, mandatory: true, signatureMethods: { clickToSign: { enabled: true } } },
			{ name: 'email', enabled: false, mandatory: false },
		]),
	)

	await page.goto('./apps/libresign')
	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	await page.getByRole('button', { name: 'Send' }).click()
	await page.getByRole('button', { name: 'Add signer' }).click()
	await page.getByPlaceholder('Account').fill('a')
	await page.getByRole('option', { name: 'admin@email.tld' }).click()
	await page.getByRole('textbox', { name: 'Signer name' }).click()
	await page.getByRole('textbox', { name: 'Signer name' }).press('ControlOrMeta+a')
	await page.getByRole('textbox', { name: 'Signer name' }).fill('Admin Name')

	// Save the signer first, then open the signature positions modal
	const createRequestResponsePromise = page.waitForResponse(response =>
		response.url().includes('/apps/libresign/api/v1/request-signature')
		&& ['POST', 'PATCH'].includes(response.request().method()),
	)
	await page.getByRole('button', { name: 'Save' }).click()
	const createRequestResponse = await createRequestResponsePromise
	const createRequestBody = await createRequestResponse.json()
	const requestUuid = createRequestBody.ocs.data.uuid as string
	await expect(setupSignaturePositionsButton).toBeVisible()
	await setupSignaturePositionsButton.click()
	const signaturePositionsDialog = page.getByLabel('Signature positions')
	const visiblePageOverlay = signaturePositionsDialog.locator('.overlay[aria-label="Page 1 of 1."]:visible').first()
	await expect(signaturePositionsDialog).toBeVisible()
	await expect(visiblePageOverlay).toBeVisible()

	// Select the signer to enter element-placement mode
	await signaturePositionsDialog.getByRole('link', { name: 'Edit signer Admin Name' }).click()
	await expect(page.getByText('Click on the place you want to add.')).toBeVisible()

	// Placing a signature element requires three steps:
	// 1. hover() triggers handleMouseMove, setting previewVisible=true inside a rAF callback.
	// 2. Waiting for .preview-element confirms the rAF ran.
	// 3. click() fires mouseup, which calls finishAdding() and places the element.
	const overlay = signaturePositionsDialog.locator('.overlay[aria-label="Page 1 of 1. Press Enter or Space to place the signature here."]:visible').first()
	await overlay.hover()
	await signaturePositionsDialog.locator('.preview-element').first().waitFor({ state: 'visible' })
	await overlay.click()

	await expect(
		signaturePositionsDialog.getByRole('img', { name: 'Signature position for Admin Name' }),
	).toBeVisible()

	// Save closes the modal and persists the element via API
	await page.getByLabel('Signature positions').getByRole('button', { name: 'Save' }).click()

	// Open the document again through the Files route using the request uuid to force a fresh load
	await reopenFileFromUuid(requestUuid)

	// Verify the element survived the round-trip to the server
	await expect(
		page.getByLabel('Signature positions').getByRole('img', { name: 'Signature position for Admin Name' }),
	).toBeVisible()

	// Select the element so the toolbar (Duplicate / Delete) appears, then delete it
	await page.getByLabel('Signature positions').getByRole('img', { name: 'Signature position for Admin Name' }).click()
	await page.getByLabel('Signature positions').getByRole('button', { name: 'Delete' }).click()

	await expect(
		page.getByLabel('Signature positions').getByRole('img', { name: 'Signature position for Admin Name' }),
	).toBeHidden()

	// Save the now-empty element list
	await page.getByLabel('Signature positions').getByRole('button', { name: 'Save' }).click()

	// Navigate away and back to force a fresh load from the server
	await page.getByRole('link', { name: 'Request' }).click()

	// Re-open the document one last time and confirm the element is gone
	await reopenFileFromUuid(requestUuid)
	await expect(signaturePositionsDialog.locator('.overlay[aria-label="Page 1 of 1."]:visible').first()).toBeVisible()

	await expect(
		signaturePositionsDialog.getByRole('img', { name: 'Signature position for Admin Name' }),
	).toBeHidden()
})
