/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Bug reproduction: Validation screen doesn't display data correctly for envelopes with 2+ files
 */

import { expect, test } from '@playwright/test'
import type { APIRequestContext, Page } from '@playwright/test'
import { configureOpenSsl, setSystemPolicy } from '../support/nc-provisioning'
import { createMailpitClient, extractSignLink, waitForEmailTo } from '../support/mailpit'

type EnvelopeSigningScenario = {
	envelopeName: string
	signerEmail: string
	signerName: string
}

type OcsEnvelopeChildSigner = {
	signRequestId?: number
	email?: string
	displayName?: string
}

type OcsEnvelopeChildFile = {
	id?: number
	name?: string
	signers?: OcsEnvelopeChildSigner[]
}

type OcsEnvelopeResponse = {
	uuid?: string
	files?: OcsEnvelopeChildFile[]
}

function buildSigningScenario(): EnvelopeSigningScenario {
	return {
		envelopeName: `Envelope Validation Bug - ${Date.now()}`,
		signerEmail: 'signer-validation@libresign.coop',
		signerName: 'Validation Tester',
	}
}

async function requestLibreSignApiAsAdmin(
	request: APIRequestContext,
	method: 'POST' | 'PATCH',
	path: string,
	body: Record<string, unknown>,
) {
	const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
	const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
	const auth = 'Basic ' + Buffer.from(`${adminUser}:${adminPassword}`).toString('base64')
	const response = await request.fetch(`./ocs/v2.php/apps/libresign/api/v1${path}`, {
		method,
		headers: {
			'OCS-ApiRequest': 'true',
			Accept: 'application/json',
			Authorization: auth,
			'Content-Type': 'application/json',
		},
		data: JSON.stringify(body),
		failOnStatusCode: false,
	})

	if (!response.ok()) {
		throw new Error(`LibreSign OCS request failed: ${method} ${path} -> ${response.status()} ${await response.text()}`)
	}

	return response.json() as Promise<{ ocs: { data: OcsEnvelopeResponse } }>
}

async function enableEnvelopeScenario(request: APIRequestContext) {
	await configureOpenSsl(request, 'LibreSign Test', {
		C: 'BR',
		OU: ['Organization Unit'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})

	await setSystemPolicy(request, 'envelope_enabled', '1')
	await setSystemPolicy(
		request,
		'identify_methods',
		JSON.stringify([
			{ name: 'account', enabled: false, mandatory: false },
			{ name: 'email', enabled: true, mandatory: true, signatureMethods: { clickToSign: { enabled: true } }, can_create_account: false },
		]),
	)
}

async function createEnvelopeWithMultipleFiles(
	request: APIRequestContext,
	scenario: EnvelopeSigningScenario,
) {
	const pdfResponse = await request.get('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf', {
		failOnStatusCode: true,
	})
	const pdfBase64 = Buffer.from(await pdfResponse.body()).toString('base64')

	const createResponse = await requestLibreSignApiAsAdmin(request, 'POST', '/request-signature', {
		name: scenario.envelopeName,
		files: [
			{ name: 'document-1.pdf', base64: pdfBase64 },
			{ name: 'document-2.pdf', base64: pdfBase64 },
		],
		signers: [{
			displayName: scenario.signerName,
			identifyMethods: [{
				method: 'email',
				value: scenario.signerEmail,
				mandatory: 1,
			}],
		}],
	})

	const envelope = createResponse.ocs.data

	if (!envelope.uuid) {
		throw new Error('Failed to create envelope with multiple files')
	}

	// Activate the envelope
	await requestLibreSignApiAsAdmin(request, 'PATCH', '/request-signature', {
		uuid: envelope.uuid,
		status: 1,
	})

	return envelope
}

async function waitForSignerInvitationLink(signerEmail: string) {
	const email = await waitForEmailTo(
		createMailpitClient(),
		signerEmail,
		'LibreSign: There is a file for you to sign',
	)
	const signLink = extractSignLink(email.Text)
	if (!signLink) {
		throw new Error('Sign link not found in email')
	}
	return signLink
}

async function openInvitationAsExternalSigner(page: Page, signLink: string) {
	// API setup runs as admin. Clear cookies so the browser behaves like the real external signer.
	await page.context().clearCookies()
	await page.goto(signLink)
}

async function defineClickToSignature(page: Page) {
	// Wait for click-to-sign button
	await expect(page.getByRole('button', { name: 'Sign the document.' })).toBeVisible({ timeout: 5_000 })
}

async function finishSigning(page: Page) {
	const signButton = page.getByRole('button', { name: 'Sign the document.' })
	await signButton.scrollIntoViewIfNeeded()
	await signButton.click()
	await page.getByRole('button', { name: 'Sign document' }).click()
}

test('validation screen should display all data correctly for envelope with 2 files', async ({ page }) => {
	const scenario = buildSigningScenario()
	const mailpit = createMailpitClient()

	await test.step('Given the system is configured to allow envelope signing via e-mail', async () => {
		await enableEnvelopeScenario(page.request)
	})

	await test.step('And an envelope with two files is created', async () => {
		await mailpit.deleteMessages()
		await createEnvelopeWithMultipleFiles(page.request, scenario)
	})

	await test.step('When the external signer opens the invitation link', async () => {
		const signLink = await waitForSignerInvitationLink(scenario.signerEmail)
		await openInvitationAsExternalSigner(page, signLink)
	})

	await test.step('And completes the signing process with click-to-sign', async () => {
		await defineClickToSignature(page)
		await finishSigning(page)
	})

	await test.step('Then the validation screen should display the envelope information correctly', async () => {
		// Wait for validation page to load
		await page.waitForURL('**/validation/**')

		// Verify envelope information section is visible
		await expect(page.getByText('Envelope information')).toBeVisible()

		// Verify envelope name is displayed
		const envelopeName = page.locator('h2.app-sidebar-header__mainname')
		await expect(envelopeName).toHaveText(scenario.envelopeName)

		// Verify documents in envelope section exists
		await expect(page.getByText('Documents in this envelope')).toBeVisible()

		await expect(page.getByText('Number of documents:')).toBeVisible()

		// Get the documents list
		const documentsList = page.locator('ul.documents-list li.document-item')
		const documentsCount = await documentsList.count()

		console.log(`Found ${documentsCount} documents in the list`)
		expect(documentsCount).toBe(2)

		// Success message should be visible
		await expect(page.getByText('Congratulations you have digitally signed a document using LibreSign')).toBeVisible()
	})
})
