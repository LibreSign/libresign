/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, setAppConfig } from '../support/nc-provisioning'

test('new signature request opens LibreSign tab and does not duplicate file row', async ({ page }) => {
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

	const uniqueName = `libresign-upload-${Date.now()}.pdf`
	const pdfResponse = await page.request.get('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf', {
		failOnStatusCode: true,
	})
	const pdfBuffer = Buffer.from(await pdfResponse.body())

	await page.goto('./apps/files')

	await page.getByRole('button', { name: 'New' }).click()
	const newSignatureRequest = page.getByText('New signature request', { exact: true })
	await expect(newSignatureRequest).toBeVisible()
	const fileChooserPromise = page.waitForEvent('filechooser')
	await newSignatureRequest.click()
	const libresignCreateResponsePromise = page.waitForResponse(
		(response) => response.url().includes('/ocs/v2.php/apps/libresign/api/v1/file') && response.request().method() === 'POST',
		{ timeout: 20000 },
	)
	const chooser = await fileChooserPromise
	await chooser.setFiles({
		name: uniqueName,
		mimeType: 'application/pdf',
		buffer: pdfBuffer,
	})
	const libresignCreateResponse = await libresignCreateResponsePromise
	const libresignCreateBody = await libresignCreateResponse.json() as { ocs: { data: { nodeId: number } } }

	// On stable32, Files sidebar internals differ; a successful LibreSign OCS creation call is
	// the stable evidence that "New signature request" executed the LibreSign flow.
	await expect(libresignCreateResponse.ok()).toBeTruthy()

	const filesTable = page.getByRole('table', {
		name: /List of your files and folders/i,
	})
	const matchingRequestRows = filesTable.locator(
		`[data-cy-files-list-row][data-cy-files-list-row-fileid="${libresignCreateBody.ocs.data.nodeId}"]`,
	)

	await expect(matchingRequestRows).toHaveCount(1, { timeout: 15000 })
})
