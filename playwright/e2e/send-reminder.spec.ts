/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { configureOpenSsl, setSystemPolicy } from '../support/nc-provisioning'
import { createMailpitClient, waitForEmailTo } from '../support/mailpit'

/**
 * After an admin sends a signature request, they can re-notify a signer who
 * has not yet signed by using the "Send reminder" action in the signer row.
 * This test verifies that clicking "Send reminder" causes a second notification
 * email to be delivered to the signer's mailbox.
 */
test('admin can send a reminder to a pending signer', async ({ page }) => {
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
			{ name: 'account', enabled: false, mandatory: false },
			{ name: 'email', enabled: true, mandatory: true, signatureMethods: { clickToSign: { enabled: true } }, can_create_account: false },
		]),
	)

	const mailpit = createMailpitClient()
	await mailpit.deleteMessages()

	await page.goto('./apps/libresign')
	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	await page.getByRole('button', { name: 'Send' }).click()

	// Only the email method is active — no tabs in the Add signer dialog
	await page.getByRole('button', { name: 'Add signer' }).click()
	await page.getByPlaceholder('Email').click()
	await page.getByPlaceholder('Email').pressSequentially('signer01@libresign.coop', { delay: 50 })
	await page.getByRole('option', { name: 'signer01@libresign.coop' }).click()
	await page.getByRole('textbox', { name: 'Signer name' }).fill('Signer 01')
	await page.getByRole('button', { name: 'Save' }).click()

	await page.getByRole('button', { name: 'Request signatures' }).click()
	await page.getByRole('button', { name: 'Send' }).click()

	// Confirm the initial notification email arrived — one email so far.
	await waitForEmailTo(mailpit, 'signer01@libresign.coop', 'LibreSign: There is a file for you to sign')
	const afterInitial = await mailpit.searchMessages({ query: 'to:signer01@libresign.coop subject:"LibreSign: There is a file for you to sign"' })
	expect(afterInitial.messages).toHaveLength(1)

	// Find the signer row and click "Send reminder" from its action menu.
	// The signer row renders as NcListItem with force-display-actions, so the
	// three-dots NcActions toggle is always visible (aria-label="Actions").
	await page.locator('li').filter({ hasText: 'Signer 01' }).getByRole('button', { name: 'Actions' }).click()
	const sendReminderAction = page.locator('[role="menuitem"], [role="dialog"] button').filter({ hasText: /^Send reminder$/i }).first()
	await expect(sendReminderAction).toBeVisible({ timeout: 8000 })
	await sendReminderAction.click()

	// The reminder uses a different subject: "LibreSign: Changes into a file for you to sign".
	await waitForEmailTo(mailpit, 'signer01@libresign.coop', 'LibreSign: Changes into a file for you to sign')
	const afterReminder = await mailpit.searchMessages({ query: 'to:signer01@libresign.coop subject:"LibreSign: Changes into a file for you to sign"' })
	expect(afterReminder.messages).toHaveLength(1)
})
