/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import type { APIRequestContext, Page } from '@playwright/test'
import { configureOpenSsl, setSystemPolicy } from '../support/nc-provisioning'
import { createMailpitClient, extractSignLink, waitForEmailTo } from '../support/mailpit'

/**
 * Issue #7344 in plain words:
 * an external signer receives an envelope with two PDFs, the visible signature
 * box exists only inside one child PDF, and the signer must still be able to
 * create the signature and finish the signing flow.
 */

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
		envelopeName: `Envelope with visible signature ${Date.now()}`,
		signerEmail: 'signer01@libresign.coop',
		signerName: 'Signer 01',
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

async function createEnvelopeWithVisibleSignatureRequirement(
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
			{ name: 'issue-7344-a.pdf', base64: pdfBase64 },
			{ name: 'issue-7344-b.pdf', base64: pdfBase64 },
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
	const targetFile = envelope.files?.[0]
	const targetSigner = targetFile?.signers?.find((signer) => {
		return signer.email === scenario.signerEmail || signer.displayName === scenario.signerName
	})

	if (!envelope.uuid || !targetFile?.id || !targetSigner?.signRequestId) {
		throw new Error('Failed to create envelope payload for issue #7344 e2e test')
	}

	await requestLibreSignApiAsAdmin(request, 'PATCH', '/request-signature', {
		uuid: envelope.uuid,
		status: 1,
		visibleElements: [{
			type: 'signature',
			fileId: targetFile.id,
			signRequestId: targetSigner.signRequestId,
			coordinates: {
				page: 1,
				left: 32,
				top: 48,
				width: 180,
				height: 64,
			},
		}],
	})
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

async function defineVisibleSignature(page: Page) {
	const deleteSignatureButton = page.getByRole('button', { name: 'Delete signature' })
	await deleteSignatureButton.waitFor({ state: 'visible', timeout: 3_000 }).catch(() => null)
	if (await deleteSignatureButton.isVisible()) {
		await deleteSignatureButton.click()
	}

	await expect(page.getByRole('button', { name: 'Define your signature.' })).toBeVisible()
	await page.getByRole('button', { name: 'Define your signature.' }).click()

	const signatureDialog = page.getByRole('dialog', { name: 'Customize your signatures' })
	await expect(signatureDialog).toBeVisible()
	await signatureDialog.locator('canvas').click({
		position: {
			x: 156,
			y: 132,
		},
	})
	await signatureDialog.getByRole('button', { name: 'Save' }).click()

	const confirmDialog = page.getByLabel('Confirm your signature')
	await expect(confirmDialog).toBeVisible()
	await confirmDialog.getByRole('button', { name: 'Save' }).click()

	await expect(page.getByRole('button', { name: 'Sign the document.' })).toBeVisible()
}

async function finishSigning(page: Page) {
	await page.getByRole('button', { name: 'Sign the document.' }).click()
	await page.getByRole('button', { name: 'Sign document' }).click()
}

async function expectEnvelopeSigned(page: Page, envelopeName: string) {
	await page.waitForURL('**/validation/**')
	await expect(page.getByText('Envelope information')).toBeVisible()
	await expect(page.getByText('Documents in this envelope')).toBeVisible()
	await expect(page.getByText('Congratulations you have digitally signed a document using LibreSign')).toBeVisible()
	await expect(page.locator('h2.app-sidebar-header__mainname')).toHaveText(envelopeName)
	await expect(page.getByText('You need to define a visible signature or initials to sign this document.')).not.toBeVisible()
}

test('unauthenticated signer can define a visible signature for an envelope with multiple PDFs', async ({ page }) => {
	const scenario = buildSigningScenario()
	const mailpit = createMailpitClient()

	await test.step('Given the system is configured to allow envelope signing via e-mail', async () => {
		await enableEnvelopeScenario(page.request)
	})

	await test.step('And an envelope with two PDFs is created requiring a visible signature on the first document', async () => {
		await mailpit.deleteMessages()
		await createEnvelopeWithVisibleSignatureRequirement(page.request, scenario)
	})

	await test.step('When the external signer opens the invitation link received by e-mail', async () => {
		const signLink = await waitForSignerInvitationLink(scenario.signerEmail)
		await openInvitationAsExternalSigner(page, signLink)
	})

	await test.step('And the signer draws and saves their visible signature on the document', async () => {
		await defineVisibleSignature(page)
	})

	await test.step('And the signer submits the signed document', async () => {
		await finishSigning(page)
	})

	await test.step('Then the success confirmation screen is shown with the envelope name', async () => {
		await expectEnvelopeSigned(page, scenario.envelopeName)
	})
})
