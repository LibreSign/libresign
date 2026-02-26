/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, setAppConfig } from '../support/nc-provisioning'

test('sign herself with click to sign', async ({ page }) => {
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

	await page.goto('./apps/libresign');
	await page.getByRole('button', { name: 'Upload from URL' }).click();
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf');
	await page.getByRole('button', { name: 'Send' }).click();
	await page.getByRole('button', { name: 'Add signer' }).click();
	await page.getByPlaceholder('Account').fill('admin');
	await page.getByText('admin@email.tld').click();
	await page.getByRole('button', { name: 'Save' }).click();
	await page.getByRole('button', { name: 'Request signatures' }).click();
	await page.getByRole('button', { name: 'Send' }).click();
	await page.getByRole('button', { name: 'Sign document' }).click();
	await page.getByRole('button', { name: 'Sign the document.' }).click();
	await page.getByRole('button', { name: 'Confirm' }).click();
	await page.waitForURL('**/validation/**');
	await expect(page.getByText('This document is valid')).toBeVisible();
	await page.getByRole('button', { name: 'Expand details' }).click();
	await page.getByRole('button', { name: 'Expand validation status', exact: true }).click();
	await expect(page.getByRole('link', { name: 'Document integrity verified' })).toBeVisible();
	await page.getByRole('button', { name: 'Expand document certification', exact: true }).click();
	await expect(page.getByRole('link', { name: 'Document has not been' })).toBeVisible();
});
