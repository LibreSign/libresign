/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { APIRequestContext, Locator, Page } from '@playwright/test'
import { login } from '../support/nc-login'
import {
	configureOpenSsl,
	deleteUser,
	ensureUserExists,
	ensureUserInGroup,
	setSystemPolicy,
} from '../support/nc-provisioning'
import {
	createAuthenticatedRequestContext,
	getSystemPolicySnapshot,
	restoreSystemPolicySnapshot,
	type SystemPolicySnapshot,
} from '../support/policy-api'
import { clickAddObserver, clickAddSigner, selectEmailSigner } from '../support/request-signature'
import { useRequestSignPolicyGuard } from '../support/system-policies'

useRequestSignPolicyGuard()

const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPass = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
const REQUESTER_USER = 'pw_warn_no_sig_requester'
const REQUESTER_PASS = 'requester123456'

let activeAdminCtx: APIRequestContext | null = null
let originalObserverProfile: SystemPolicySnapshot | null = null
let originalIdentifyMethods: SystemPolicySnapshot | null = null

function getVisiblePdfOverlay(dialog: Locator) {
	return dialog.locator('.overlay:visible').first()
}

async function uploadSamplePdf(page: Page) {
	await page.goto('./apps/libresign')
	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill(
		'https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf',
	)
	await page.getByRole('button', { name: 'Send' }).click()
	await expect(page.locator('#request-signature-tab')).toBeVisible({ timeout: 15_000 })
}

async function addEmailSigner(page: Page, email: string, name: string) {
	await clickAddSigner(page)
	await selectEmailSigner(page, email)
	await page.getByRole('textbox', { name: 'Signer name' }).fill(name)
	await page.getByRole('button', { name: 'Save' }).click()
	await expect(page.locator('.participants-section').getByText(name)).toBeVisible({ timeout: 10_000 })
}

async function addEmailObserver(page: Page, email: string, name: string) {
	await clickAddObserver(page)
	await selectEmailSigner(page, email)
	await page.getByRole('textbox', { name: 'Observer name' }).fill(name)
	await page.getByRole('button', { name: 'Save' }).click()
	await expect(page.locator('.participants-section').getByText(name)).toBeVisible({ timeout: 10_000 })
}

async function placeVisibleSignatureForSigner(page: Page, signerName: string) {
	const requestSignatureTab = page.locator('#request-signature-tab')
	const setupSignaturePositionsButton = requestSignatureTab.getByRole('button', { name: 'Setup signature positions' })
	const signaturePositionsDialog = page.getByLabel('Signature positions')

	await expect(setupSignaturePositionsButton).toBeVisible({ timeout: 10_000 })
	await setupSignaturePositionsButton.click()
	await expect(signaturePositionsDialog).toBeVisible({ timeout: 15_000 })

	const overlay = getVisiblePdfOverlay(signaturePositionsDialog)
	await expect(overlay).toBeVisible({ timeout: 30_000 })

	const editSignerLink = signaturePositionsDialog.getByRole('link', { name: `Edit signer ${signerName}` })
	await expect(editSignerLink).toBeVisible({ timeout: 10_000 })
	await editSignerLink.click()

	const addInstruction = signaturePositionsDialog.getByText('Click on the place you want to add.')
	await expect(addInstruction).toBeVisible()
	await expect(editSignerLink).toBeHidden()

	await overlay.hover()
	await signaturePositionsDialog.locator('.preview-element').first().waitFor({ state: 'visible' })
	await overlay.click()

	await expect(addInstruction).toBeHidden()
	await expect(editSignerLink).toBeVisible()
	await signaturePositionsDialog.getByRole('button', { name: 'Save' }).click()
	await expect(signaturePositionsDialog).toBeHidden({ timeout: 10_000 })
}

test.describe('Warn requesters when signers have no visible signature field (#8323)', () => {
	test.describe.configure({ mode: 'serial' })

	test.beforeEach(async ({ page }) => {
		await login(page.request, adminUser, adminPass)

		await configureOpenSsl(page.request, 'LibreSign Test', {
			C: 'BR',
			OU: ['Organization Unit'],
			ST: 'Rio de Janeiro',
			O: 'LibreSign',
			L: 'Rio de Janeiro',
		})

		activeAdminCtx = await createAuthenticatedRequestContext(adminUser, adminPass)
		// System policies have supported GET/POST endpoints in PolicyController and can be reliably snapshotted/restored.
		originalObserverProfile = await getSystemPolicySnapshot(activeAdminCtx, 'enable_observer_profile')
		originalIdentifyMethods = await getSystemPolicySnapshot(activeAdminCtx, 'identify_methods')

		await setSystemPolicy(page.request, 'enable_observer_profile', JSON.stringify(true))
		await setSystemPolicy(
			page.request,
			'identify_methods',
			JSON.stringify({
				can_create_account: false,
				factors: [
					{ name: 'account', enabled: false, requirement: 'optional' },
					{ name: 'email', enabled: true, requirement: 'required', signatureMethods: { clickToSign: { enabled: true } } },
				],
			}),
		)

		// Ensure disposable dedicated requester user starts fresh: verify deletion at transport and OCS levels without blanket catch
		const preDeleteResult = await deleteUser(activeAdminCtx, REQUESTER_USER, adminUser, adminPass)
		expect([200, 404, 998]).toContain(preDeleteResult.ocs?.meta?.statuscode)
		await ensureUserExists(page.request, REQUESTER_USER, REQUESTER_PASS)
		await ensureUserInGroup(page.request, REQUESTER_USER, 'admin')

		// Run all #8323 requester interactions as the dedicated disposable user
		await login(page.request, REQUESTER_USER, REQUESTER_PASS)
	})

	test.afterEach(async () => {
		const adminCtx = activeAdminCtx ?? await createAuthenticatedRequestContext(adminUser, adminPass)
		try {
			// Restore reliably snapshotted system policies
			if (originalObserverProfile) {
				await restoreSystemPolicySnapshot(adminCtx, 'enable_observer_profile', originalObserverProfile)
			}
			if (originalIdentifyMethods) {
				await restoreSystemPolicySnapshot(adminCtx, 'identify_methods', originalIdentifyMethods)
			}
		} finally {
			try {
				// Delete disposable dedicated requester user and verify deletion at transport and OCS levels
				const deleteResult = await deleteUser(adminCtx, REQUESTER_USER, adminUser, adminPass)
				expect([200, 404, 998]).toContain(deleteResult.ocs?.meta?.statuscode)
			} finally {
				await adminCtx.dispose()
				activeAdminCtx = null
				originalObserverProfile = null
				originalIdentifyMethods = null
			}
		}
	})

	test('shows warning when signers lack visible signature fields, but allows sending', async ({ page }) => {
		await uploadSamplePdf(page)
		await addEmailSigner(page, 'signer01@example.com', 'Alice Signer')

		const requestSignaturesButton = page.getByRole('button', { name: 'Request signatures' })
		await expect(requestSignaturesButton).toBeVisible()
		await requestSignaturesButton.click()

		const confirmDialog = page.getByRole('dialog', { name: 'Confirm' })
		await expect(confirmDialog).toBeVisible()
		await expect(confirmDialog.getByText('Send signature request?')).toBeVisible()
		await expect(confirmDialog.getByText('Some signers have no visible signature field.')).toBeVisible()
		await expect(confirmDialog.getByText('No visible signature:')).toBeVisible()
		await expect(confirmDialog.getByText('Alice Signer')).toBeVisible()
		await expect(
			confirmDialog.getByText('Do not warn me again when signers have no visible signature field'),
		).toBeVisible()

		// Request can still be sent
		await confirmDialog.getByRole('button', { name: 'Send' }).click()
		await expect(confirmDialog).toBeHidden()
	})

	test('does not show warning when all signing participants have visible signature fields', async ({ page }) => {
		await uploadSamplePdf(page)
		await addEmailSigner(page, 'signer01@example.com', 'Alice Signer')
		await placeVisibleSignatureForSigner(page, 'Alice Signer')

		const requestSignaturesButton = page.getByRole('button', { name: 'Request signatures' })
		await requestSignaturesButton.click()

		const confirmDialog = page.getByRole('dialog', { name: 'Confirm' })
		await expect(confirmDialog).toBeVisible()
		await expect(confirmDialog.getByText('Send signature request?')).toBeVisible()
		await expect(confirmDialog.getByText('Some signers have no visible signature field.')).toHaveCount(0)

		await confirmDialog.getByRole('button', { name: 'Send' }).click()
		await expect(confirmDialog).toBeHidden()
	})

	test('mixed signers: only lists affected signers in the warning', async ({ page }) => {
		await uploadSamplePdf(page)
		await addEmailSigner(page, 'signer01@example.com', 'Alice Covered')
		await addEmailSigner(page, 'signer02@example.com', 'Bob Uncovered')
		await placeVisibleSignatureForSigner(page, 'Alice Covered')

		await page.getByRole('button', { name: 'Request signatures' }).click()

		const confirmDialog = page.getByRole('dialog', { name: 'Confirm' })
		await expect(confirmDialog).toBeVisible()
		await expect(confirmDialog.getByText('Some signers have no visible signature field.')).toBeVisible()
		await expect(confirmDialog.locator('ul').getByText('Bob Uncovered')).toBeVisible()
		await expect(confirmDialog.locator('ul').getByText('Alice Covered')).toHaveCount(0)
	})

	test('observers are ignored and do not trigger warning', async ({ page }) => {
		await uploadSamplePdf(page)
		await addEmailSigner(page, 'signer01@example.com', 'Alice Signer')
		await addEmailObserver(page, 'observer01@example.com', 'Dave Observer')
		await placeVisibleSignatureForSigner(page, 'Alice Signer')

		await page.getByRole('button', { name: 'Request signatures' }).click()

		const confirmDialog = page.getByRole('dialog', { name: 'Confirm' })
		await expect(confirmDialog).toBeVisible()
		await expect(confirmDialog.getByText('Send signature request?')).toBeVisible()
		await expect(confirmDialog.getByText('Some signers have no visible signature field.')).toHaveCount(0)
		await expect(confirmDialog.getByText('Dave Observer')).toHaveCount(0)
	})

	test('individual request flow only checks the selected signer from a valid non-draft state', async ({ page }) => {
		await uploadSamplePdf(page)
		await addEmailSigner(page, 'signer01@example.com', 'Alice Signer')
		await placeVisibleSignatureForSigner(page, 'Alice Signer')

		// Request signatures initially so the document transitions to valid non-DRAFT state (FILE_STATUS.SIGNING_IN_PROGRESS)
		await page.getByRole('button', { name: 'Request signatures' }).click()
		const initialConfirm = page.getByRole('dialog', { name: 'Confirm' })
		await expect(initialConfirm).toBeVisible()
		await initialConfirm.getByRole('button', { name: 'Send' }).click()
		await expect(initialConfirm).toBeHidden()

		// Add two new signers without visible signatures: Bob and Charlie
		await addEmailSigner(page, 'signer02@example.com', 'Bob Uncovered')
		await addEmailSigner(page, 'signer03@example.com', 'Charlie Uncovered')

		// Open action menu for Bob and trigger individual "Request signature"
		const bobRow = page.locator('li').filter({ hasText: 'Bob Uncovered' }).first()
		await bobRow.getByRole('button', { name: 'Actions' }).click()

		const requestSignAction = page
			.getByRole('menuitem', { name: 'Request signature', exact: true })
			.or(page.getByRole('button', { name: 'Request signature', exact: true }))
			.filter({ visible: true })

		await expect(requestSignAction).toHaveCount(1)
		await requestSignAction.click()

		const confirmDialog = page.getByRole('dialog', { name: 'Confirm' })
		await expect(confirmDialog).toBeVisible()
		await expect(confirmDialog.getByText('Some signers have no visible signature field.')).toBeVisible()
		await expect(confirmDialog.getByText('No visible signature:')).toBeVisible()

		// Proves only the selected signer (Bob) is checked and listed; Charlie and Alice are not listed
		await expect(confirmDialog.locator('ul').getByText('Bob Uncovered')).toBeVisible()
		await expect(confirmDialog.locator('ul').getByText('Charlie Uncovered')).toHaveCount(0)
		await expect(confirmDialog.locator('ul').getByText('Alice Signer')).toHaveCount(0)

		await confirmDialog.getByRole('button', { name: 'Cancel' }).click()
		await expect(confirmDialog).toBeHidden()
	})

	test('cancelling or closing confirmation dialog does not save preference', async ({ page }) => {
		await uploadSamplePdf(page)
		await addEmailSigner(page, 'signer01@example.com', 'Alice Signer')

		// 1. Open confirmation and toggle checkbox
		await page.getByRole('button', { name: 'Request signatures' }).click()
		let confirmDialog = page.getByRole('dialog', { name: 'Confirm' })
		await expect(confirmDialog).toBeVisible()

		const checkbox = confirmDialog.getByRole('checkbox')
		await expect(checkbox).not.toBeChecked()
		await confirmDialog.getByText(
			'Do not warn me again when signers have no visible signature field',
			{ exact: true },
		).click()
		await expect(checkbox).toBeChecked()

		// Cancel dialog
		await confirmDialog.getByRole('button', { name: 'Cancel' }).click()
		await expect(confirmDialog).toBeHidden()

		// Reopen confirmation: warning must still appear and checkbox must be unchecked
		await page.getByRole('button', { name: 'Request signatures' }).click()
		confirmDialog = page.getByRole('dialog', { name: 'Confirm' })
		await expect(confirmDialog).toBeVisible()
		await expect(confirmDialog.getByText('Some signers have no visible signature field.')).toBeVisible()
		await expect(confirmDialog.getByRole('checkbox')).not.toBeChecked()

		// 2. Test closing via close button / ESC
		await confirmDialog.getByText(
			'Do not warn me again when signers have no visible signature field',
			{ exact: true },
		).click()
		await confirmDialog.getByRole('button', { name: 'Close' }).or(confirmDialog.locator('.nc-dialog__close')).click()
		await expect(confirmDialog).toBeHidden()

		// Reopen confirmation: warning must still appear and checkbox must be unchecked
		await page.getByRole('button', { name: 'Request signatures' }).click()
		confirmDialog = page.getByRole('dialog', { name: 'Confirm' })
		await expect(confirmDialog).toBeVisible()
		await expect(confirmDialog.getByText('Some signers have no visible signature field.')).toBeVisible()
		await expect(confirmDialog.getByRole('checkbox')).not.toBeChecked()
	})

	test('selecting do not warn again and clicking Send suppresses warning for subsequent requests', async ({ page }) => {
		await uploadSamplePdf(page)
		await addEmailSigner(page, 'signer01@example.com', 'Alice Signer')

		await page.getByRole('button', { name: 'Request signatures' }).click()
		let confirmDialog = page.getByRole('dialog', { name: 'Confirm' })
		await expect(confirmDialog).toBeVisible()

		const checkbox = confirmDialog.getByRole('checkbox')
		await confirmDialog.getByText(
			'Do not warn me again when signers have no visible signature field',
			{ exact: true },
		).click()
		await confirmDialog.getByRole('button', { name: 'Send' }).click()
		await expect(confirmDialog).toBeHidden()

		// Create another request with missing visible signatures
		await uploadSamplePdf(page)
		await addEmailSigner(page, 'signer02@example.com', 'Bob Signer')

		await page.getByRole('button', { name: 'Request signatures' }).click()
		confirmDialog = page.getByRole('dialog', { name: 'Confirm' })
		await expect(confirmDialog).toBeVisible()
		await expect(confirmDialog.getByText('Send signature request?')).toBeVisible()
		await expect(confirmDialog.getByText('Some signers have no visible signature field.')).toHaveCount(0)
	})
})
