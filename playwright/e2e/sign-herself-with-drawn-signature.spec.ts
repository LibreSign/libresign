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

test('sign herself with drawn signature', async ({ page }) => {
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
	await page.getByRole('button', { name: 'Upload from URL' }).click();
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).click();
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf');
	await page.getByRole('button', { name: 'Send' }).click();
	await page.getByRole('button', { name: 'Add signer' }).click();
	await page.getByPlaceholder('Account').click();
	await page.getByPlaceholder('Account').fill('a');
	await page.getByRole('option', { name: 'admin@email.tld' }).click();

	await page.getByRole('textbox', { name: 'Signer name' }).click();
	await page.getByRole('textbox', { name: 'Signer name' }).press('ControlOrMeta+a');
	await page.getByRole('textbox', { name: 'Signer name' }).fill('Admin Name');


	await page.getByRole('button', { name: 'Save' }).click();
	await page.getByRole('button', { name: 'Setup signature positions' }).click();
	const signaturePositionsDialog = page.getByLabel('Signature positions')
	const pageOverlay = getVisiblePdfOverlay(signaturePositionsDialog)
	const addInstruction = signaturePositionsDialog.getByText('Click on the place you want to add.')
	const cancelPlacementButton = signaturePositionsDialog.getByRole('button', { name: 'Cancel' })
	const editSignerLink = signaturePositionsDialog.getByRole('link', { name: 'Edit signer Admin Name' })
	await expect(signaturePositionsDialog).toBeVisible()
	await expect(pageOverlay).toBeVisible()
	await editSignerLink.click();

	await expect(addInstruction).toBeVisible();
	await expect(cancelPlacementButton).toBeVisible();
	await expect(editSignerLink).toBeHidden();

	// Placing a signature element on the PDF canvas requires three steps:
	// 1. hover() triggers handleMouseMove, which sets previewVisible=true inside a
	//    requestAnimationFrame callback.
	// 2. Waiting for .preview-element confirms the rAF ran. Without this, finishAdding()
	//    (bound to mouseup on document) returns early because previewVisible is still false.
	// 3. click() fires mouseup on the document, which triggers finishAdding() and places
	//    the element at the current preview position.
	const overlay = getVisiblePdfOverlay(signaturePositionsDialog)
	await overlay.hover()
	await signaturePositionsDialog.locator('.preview-element').first().waitFor({ state: 'visible' })
	await overlay.click()
	await expect(addInstruction).toBeHidden()
	await expect(cancelPlacementButton).toBeHidden()
	await expect(editSignerLink).toBeVisible()
	await expect(
		signaturePositionsDialog.getByRole('img', { name: 'Signature position for Admin Name' })
	).toBeVisible()

	await page.getByRole('button', { name: 'Save' }).click();
	await expect(signaturePositionsDialog).toBeHidden()
	await page.getByRole('button', { name: 'Request signatures' }).click();
	await page.getByRole('button', { name: 'Send' }).click();
	await page.getByRole('button', { name: 'Sign document' }).click();
	await expect(page.getByLabel('PDF document to sign')).toBeVisible({ timeout: 15000 })

	await expect(
		page.getByLabel('PDF document to sign').getByRole('img', { name: 'Signature position for Admin Name' })
	).toBeVisible({ timeout: 15000 })

	await page.getByRole('button', { name: 'Define your signature.' }).click();

	const signatureDialog = page.getByRole('dialog', { name: 'Customize your signatures' })
	await expect(signatureDialog).toBeVisible()
	await expect(signatureDialog.locator('canvas').first()).toBeVisible()
	await signatureDialog.locator('canvas').first().click({
		position: {
			x: 156,
			y: 132,
		},
	})
	await page.getByRole('button', { name: 'Save' }).click();
	await expect(page.getByRole('heading', { name: 'Confirm your signature' })).toBeVisible();
	await page.getByLabel('Confirm your signature').getByRole('button', { name: 'Save' }).click();
	await expect(page.getByRole('button', { name: 'Sign the document.' })).toBeVisible();

	await page.getByRole('button', { name: 'Sign the document.' }).click();
	const signResponsePromise = page.waitForResponse((response) =>
		response.request().method() === 'POST'
		&& response.url().includes('/apps/libresign/api/v1/sign/'),
	)
	await page.getByRole('button', { name: 'Sign document' }).click();
	const signResponse = await signResponsePromise
	const signResponseBody = await signResponse.text()
	expect(
		signResponse.ok(),
		`Sign API failed with status ${signResponse.status()}: ${signResponseBody}`,
	).toBeTruthy()
	await expect(page.getByText('This document is valid')).toBeVisible()
	await page.getByRole('button', { name: 'Expand details' }).click()
	await page.getByRole('button', { name: 'Expand validation status', exact: true }).click()
	await expect(page.getByRole('link', { name: 'Document integrity verified' })).toBeVisible()
	await page.getByRole('button', { name: 'Expand document certification', exact: true }).click()
});
