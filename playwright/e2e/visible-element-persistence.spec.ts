/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { Locator } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, setSystemPolicy } from '../support/nc-provisioning'

function getVisiblePdfOverlay(dialog: Locator) {
	return dialog.locator('.overlay:visible').first()
}

test('visible signature element persists and can be deleted', async ({ page }) => {
	const requestSignatureTab = page.locator('#request-signature-tab')
	const setupSignaturePositionsButton = requestSignatureTab.getByRole('button', { name: 'Setup signature positions' })
	const openSidebarButton = page.getByRole('button', { name: 'Open sidebar' })
	const signaturePositionsDialog = page.getByLabel('Signature positions')

	async function reopenFileFromUuid(uuid: string) {
		await page.goto(`./apps/libresign/f/filelist/sign?uuid=${uuid}`)
		await expect(page).toHaveURL(/\/apps\/libresign\/f\/filelist\/sign/)

		const setupVisible = await setupSignaturePositionsButton.isVisible({ timeout: 3000 }).catch(() => false)
		if (!setupVisible) {
			const draftRow = page.locator('[data-cy-files-list-tbody] tr.files-list__row').filter({ hasText: 'Draft' }).first()
			await expect(draftRow).toBeVisible({ timeout: 20000 })
			await draftRow.getByRole('button').first().click()
		}

		if (await openSidebarButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await openSidebarButton.click()
		}
		await expect(setupSignaturePositionsButton).toBeVisible({ timeout: 15000 })
		await setupSignaturePositionsButton.click()
		await expect(signaturePositionsDialog).toBeVisible({ timeout: 30000 })
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

	await setSystemPolicy(
		page.request,
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
	const visiblePageOverlay = getVisiblePdfOverlay(signaturePositionsDialog)
	const addInstruction = signaturePositionsDialog.getByText('Click on the place you want to add.')
	const cancelPlacementButton = signaturePositionsDialog.getByRole('button', { name: 'Cancel' })
	const editSignerLink = signaturePositionsDialog.getByRole('link', { name: 'Edit signer Admin Name' })
	await expect(signaturePositionsDialog).toBeVisible()
	await expect(visiblePageOverlay).toBeVisible()

	// Select the signer to enter element-placement mode
	await editSignerLink.click()
	await expect(addInstruction).toBeVisible()
	await expect(cancelPlacementButton).toBeVisible()
	await expect(editSignerLink).toBeHidden()

	// Placing a signature element requires three steps:
	// 1. hover() triggers handleMouseMove, setting previewVisible=true inside a rAF callback.
	// 2. Waiting for .preview-element confirms the rAF ran.
	// 3. click() fires mouseup, which calls finishAdding() and places the element.
	const overlay = getVisiblePdfOverlay(signaturePositionsDialog)
	await overlay.hover()
	await signaturePositionsDialog.locator('.preview-element').first().waitFor({ state: 'visible' })
	await overlay.click()
	await expect(addInstruction).toBeHidden()
	await expect(cancelPlacementButton).toBeHidden()
	await expect(editSignerLink).toBeVisible()
	const signaturePosition = signaturePositionsDialog.getByRole('img', { name: /Signature position for/i }).first()

	await expect(signaturePosition).toBeVisible({ timeout: 10000 })

	// Save closes the modal and persists the element via API
	await page.getByLabel('Signature positions').getByRole('button', { name: 'Save' }).click()
	await expect(page.getByLabel('Signature positions')).toBeHidden()

	// Open the document again through the Files route using the request uuid to force a fresh load
	await reopenFileFromUuid(requestUuid)

	// Verify the element survived the round-trip to the server
	await expect(page.getByLabel('Signature positions').getByRole('img', { name: /Signature position for/i }).first()).toBeVisible({ timeout: 30000 })

	// Select the element so the toolbar (Duplicate / Delete) appears, then delete it
	await page.getByLabel('Signature positions').getByRole('img', { name: /Signature position for/i }).first().click()
	await page.getByLabel('Signature positions').getByRole('button', { name: 'Delete' }).click()

	await expect(page.getByLabel('Signature positions').getByRole('img', { name: /Signature position for/i }).first()).toBeHidden()

	// Save the now-empty element list
	await page.getByLabel('Signature positions').getByRole('button', { name: 'Save' }).click()

	// Navigate away and back to force a fresh load from the server
	await page.getByRole('link', { name: 'Request' }).click()

	// Re-open the document one last time and confirm the element is gone
	await reopenFileFromUuid(requestUuid)
	await expect(signaturePositionsDialog).toBeVisible()
	await expect(getVisiblePdfOverlay(signaturePositionsDialog)).toBeVisible({ timeout: 30000 })

	await expect(signaturePositionsDialog.getByRole('img', { name: /Signature position for/i }).first()).toBeHidden()
})
