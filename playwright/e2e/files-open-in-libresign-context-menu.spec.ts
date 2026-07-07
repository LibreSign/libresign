/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'

import { login } from '../support/nc-login'
import { ensureFilesHomeInitialized, uploadFileToFilesApp, waitForFilesAction } from '../support/nc-files'
import { configureOpenSsl } from '../support/nc-provisioning'
import { getSmallValidPdfBuffer } from '../support/pdf-fixtures'
import { useRequestSignPolicyGuard } from '../support/system-policies'

useRequestSignPolicyGuard()

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

	await ensureFilesHomeInitialized(page.request)

	const fileName = `libresign-context-menu-${Date.now()}.pdf`
	const pdfBuffer = await getSmallValidPdfBuffer()

	await page.goto('./apps/files')
	await expect(page.getByRole('heading', { name: 'All files' })).toBeVisible({ timeout: 15000 })
	await waitForFilesAction(page, 'open-in-libresign')
	await uploadFileToFilesApp(page, {
		name: fileName,
		mimeType: 'application/pdf',
		buffer: pdfBuffer,
	})

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
