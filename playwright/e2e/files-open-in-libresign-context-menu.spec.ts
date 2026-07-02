/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test, type APIRequestContext } from '@playwright/test'

import { login } from '../support/nc-login'
import { configureOpenSsl } from '../support/nc-provisioning'
import { getSmallValidPdfBuffer } from '../support/pdf-fixtures'
import { useRequestSignPolicyGuard } from '../support/system-policies'

useRequestSignPolicyGuard()

/**
 * Uploads a PDF file directly into the admin Files area using the current
 * authenticated browser session token.
 *
 * @param request Authenticated Playwright API request context.
 * @param fileName Target file name in the admin root folder.
 * @param pdfBuffer PDF fixture contents.
 * @param requestToken Nextcloud CSRF request token from the current page.
 */
async function uploadPdfToAdminFiles(
	request: APIRequestContext,
	fileName: string,
	pdfBuffer: Buffer,
	requestToken: string,
) {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const response = await request.fetch(`./remote.php/dav/files/${adminUser}/${fileName}`, {
		method: 'PUT',
		headers: {
			'Content-Type': 'application/pdf',
			requesttoken: requestToken,
		},
		data: pdfBuffer,
		failOnStatusCode: false,
	})

	if (![201, 204].includes(response.status())) {
		throw new Error(`WebDAV upload failed for ${fileName}: ${response.status()} ${await response.text()}`)
	}
}

test('open PDF in LibreSign from Files context menu', async ({ page }) => {
	test.slow()

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
	const pdfBuffer = await getSmallValidPdfBuffer()

	await page.goto('./apps/files')
	await expect(page.getByRole('heading', { name: 'All files' })).toBeVisible({ timeout: 15000 })
	const requestToken = await page.evaluate(() => {
		const nextcloudWindow = window as Window & { OC?: { requestToken?: string } }
		return nextcloudWindow.OC?.requestToken ?? ''
	})
	await uploadPdfToAdminFiles(page.request, fileName, pdfBuffer, requestToken)
	await page.reload()
	await expect(page.getByRole('heading', { name: 'All files' })).toBeVisible({ timeout: 15000 })

	const filesTable = page.getByRole('table', {
		name: /List of your files and folders/i,
	})
	const fileCheckbox = filesTable.getByRole('checkbox', {
		name: `Toggle selection for file "${fileName}"`,
	})
	await expect(fileCheckbox).toHaveCount(1, { timeout: 30000 })
	const fileRow = fileCheckbox.locator('xpath=ancestor::tr[1]')
	await expect(fileRow).toBeVisible({ timeout: 30000 })

	await fileRow.click({ button: 'right' })

	const contextMenu = page.getByRole('menu').last()
	await expect(contextMenu).toBeVisible({ timeout: 15000 })
	const openInLibreSignAction = contextMenu.getByRole('menuitem', { name: 'Open in LibreSign' })
	await expect(openInLibreSignAction).toBeVisible({ timeout: 15000 })
	await openInLibreSignAction.click()

	const libresignTab = page.getByRole('tab', { name: 'LibreSign' })
	await expect(libresignTab).toHaveAttribute('aria-selected', 'true', { timeout: 15000 })
	await expect(page.getByRole('button', { name: 'Add signer' })).toBeVisible({ timeout: 15000 })
	await expect(page.locator('.app-sidebar-header__mainname')).toHaveText(fileName, { timeout: 15000 })
})
