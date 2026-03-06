/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl } from '../support/nc-provisioning'

test('open PDF in LibreSign from Files context menu', async ({ page }) => {
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

	const fileName = `libresign-context-menu-${Date.now()}.pdf`
	const pdfResponse = await page.request.get('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf', {
		failOnStatusCode: true,
	})
	const pdfBuffer = Buffer.from(await pdfResponse.body())

	await page.goto('./apps/files')

	await page.locator('input[type="file"]').first().setInputFiles({
		name: fileName,
		mimeType: 'application/pdf',
		buffer: pdfBuffer,
	})

	const filesTable = page.getByRole('table', {
		name: /List of your files and folders/i,
	})
	const fileRow = filesTable.getByRole('row', { name: new RegExp(fileName) })
	await expect(fileRow).toBeVisible({ timeout: 15000 })

	await fileRow.click({ button: 'right' })

	const openInLibreSignAction = page.getByRole('menuitem', { name: 'Open in LibreSign' })
	await expect(openInLibreSignAction).toBeVisible()
	await openInLibreSignAction.click()

	const libresignTab = page.getByRole('tab', { name: 'LibreSign' })
	await expect(libresignTab).toHaveAttribute('aria-selected', 'true')
	await expect(page.getByRole('button', { name: 'Add signer' })).toBeVisible()
	await expect(page.locator('.app-sidebar-header__mainname')).toHaveText(fileName)
})
