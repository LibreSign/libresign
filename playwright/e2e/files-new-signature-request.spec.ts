/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'

import { login } from '../support/nc-login'
import { configureOpenSsl } from '../support/nc-provisioning'
import { getSmallValidPdfBuffer } from '../support/pdf-fixtures'
import { useRequestSignPolicyGuard } from '../support/system-policies'

useRequestSignPolicyGuard()

test('new signature request opens LibreSign tab and does not duplicate file row', async ({ page }) => {
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

	const uniqueName = `libresign-upload-${Date.now()}.pdf`
	const pdfBuffer = await getSmallValidPdfBuffer()

	await page.goto('./apps/files')
	await expect(page.getByRole('heading', { name: 'All files' })).toBeVisible({ timeout: 15000 })

	const newButton = page.getByRole('button', { name: 'New' })
	await expect(newButton).toBeVisible({ timeout: 15000 })
	await newButton.click()
	const newMenu = page.getByRole('menu', { name: 'New' })
	await expect(newMenu).toBeVisible()
	const newSignatureRequestEntry = newMenu.getByRole('menuitem', { name: 'New signature request' })
	await expect(newSignatureRequestEntry).toBeVisible()
	const fileChooserPromise = page.waitForEvent('filechooser')
	await newSignatureRequestEntry.click()
	const chooser = await fileChooserPromise
	await chooser.setFiles({
		name: uniqueName,
		mimeType: 'application/pdf',
		buffer: pdfBuffer,
	})

	const libresignTab = page.getByRole('tab', { name: 'LibreSign' })
	await expect(libresignTab).toHaveAttribute('aria-selected', 'true', { timeout: 15000 })
	await expect(page.getByRole('button', { name: 'Add signer' })).toBeVisible({ timeout: 15000 })

	const filesTable = page.getByRole('table', {
		name: /List of your files and folders/i,
	})

	const matchingFileCheckboxes = filesTable.getByRole('checkbox', {
		name: `Toggle selection for file "${uniqueName}"`,
	})

	await expect(matchingFileCheckboxes).toHaveCount(1, { timeout: 15000 })
})
