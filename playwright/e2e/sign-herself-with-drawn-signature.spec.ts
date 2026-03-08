/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { Locator } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, setAppConfig } from '../support/nc-provisioning'

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
	await expect(signaturePositionsDialog).toBeVisible()
	await expect(pageOverlay).toBeVisible()
	await signaturePositionsDialog.getByRole('link', { name: 'Edit signer Admin Name' }).click();

	await expect(page.getByText('Click on the place you want to add.')).toBeVisible();

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
	await expect(
		signaturePositionsDialog.getByRole('img', { name: 'Signature position for Admin Name' })
	).toBeVisible()

	await page.getByRole('button', { name: 'Save' }).click();
	await page.getByRole('button', { name: 'Request signatures' }).click();
	await page.getByRole('button', { name: 'Send' }).click();
	await page.getByRole('button', { name: 'Sign document' }).click();

	await expect(
		page.getByLabel('PDF document to sign').getByRole('img', { name: 'Signature position for Admin Name' })
	).toBeVisible()

	// If a signature already exists from a previous run, delete it before creating a new one
	const deleteSignatureBtn = page.getByRole('button', { name: 'Delete signature' })
	await deleteSignatureBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => null)
	if (await deleteSignatureBtn.isVisible()) {
		await deleteSignatureBtn.click()
	}

	await page.getByRole('button', { name: 'Define your signature.' }).click();

	// The signature type chooser must use role="tab" + aria-selected, not aria-pressed toggle buttons.
	// Screen readers announce role="tab" as "tab, 1 of 3" which lets blind users understand the widget.
	// With aria-pressed buttons they only hear "toggle button, pressed" with no tab count context.
	const signatureDialog = page.getByRole('dialog', { name: 'Customize your signatures' })
	await expect(signatureDialog.getByRole('tab', { name: 'Draw' })).toBeVisible()
	await expect(signatureDialog.getByRole('tab', { name: 'Text' })).toBeVisible()
	await expect(signatureDialog.getByRole('tab', { name: 'Upload' })).toBeVisible()
	await expect(signatureDialog.getByRole('tab', { name: 'Draw' })).toHaveAttribute('aria-selected', 'true')

	// Navigate to a different tab and back — verifies aria-selected updates correctly
	await signatureDialog.getByRole('tab', { name: 'Text' }).click()
	await expect(signatureDialog.getByRole('tab', { name: 'Text' })).toHaveAttribute('aria-selected', 'true')
	await expect(signatureDialog.getByRole('tab', { name: 'Draw' })).toHaveAttribute('aria-selected', 'false')
	await signatureDialog.getByRole('tab', { name: 'Draw' }).click()
	await expect(signatureDialog.getByRole('tab', { name: 'Draw' })).toHaveAttribute('aria-selected', 'true')

	await signatureDialog.locator('canvas').click({
		position: {
			x: 156,
			y: 132
		}
	});
	await page.getByRole('button', { name: 'Save' }).click();
	await expect(page.getByRole('heading', { name: 'Confirm your signature' })).toBeVisible();
	await expect(page.getByRole('img', { name: 'Signature preview' })).toBeVisible();
	await page.getByLabel('Confirm your signature').getByRole('button', { name: 'Save' }).click();

	await page.getByRole('button', { name: 'Sign the document.' }).click();
	await page.getByRole('button', { name: 'Sign document' }).click();
	await page.waitForURL('**/validation/**')
	await expect(page.getByText('This document is valid')).toBeVisible()
	await page.getByRole('button', { name: 'Expand details' }).click()
	await page.getByRole('button', { name: 'Expand validation status', exact: true }).click()
	await expect(page.getByRole('link', { name: 'Document integrity verified' })).toBeVisible()
	await page.getByRole('button', { name: 'Expand document certification', exact: true }).click()
	await expect(page.getByRole('link', { name: 'Document has not been modified after signing' })).toBeVisible()
});
