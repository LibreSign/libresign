/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/* eslint-disable jsdoc/require-jsdoc */

import { expect, test } from '@playwright/test'
import type { APIRequestContext, Locator, Page } from '@playwright/test'

import { createMailpitClient, extractSignLink, waitForEmailTo } from '../support/mailpit'
import { configureOpenSsl, setCertificateEngine, setSystemPolicy } from '../support/nc-provisioning'
import { getSmallValidPdfBase64 } from '../support/pdf-fixtures'
import { useFooterPolicyGuard } from '../support/system-policies'

useFooterPolicyGuard()
test.setTimeout(60_000)

type EnvelopeSigningScenario = {
	envelopeName: string
	signerEmail: string
	signerName: string
}

type OcsEnvelopeChildSigner = {
	signRequestId?: number
	sign_request_uuid?: string
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
	const runId = Date.now()
	return {
		envelopeName: `Envelope with visible signature ${runId}`,
		signerEmail: `visible-signature-${runId}@libresign.coop`,
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

	await setCertificateEngine(request, 'openssl')

	await setSystemPolicy(request, 'envelope_enabled', '1')
	await setSystemPolicy(request, 'identification_documents', JSON.stringify({ enabled: false, approvers: ['admin'] }))
	await setSystemPolicy(
		request,
		'identify_methods',
		JSON.stringify([
			{ name: 'account', enabled: false, mandatory: false },
			{ name: 'email', enabled: true, mandatory: true, signatureMethods: { clickToSign: { enabled: true } }, can_create_account: false },
		]),
	)
}

function findSigner(files: OcsEnvelopeChildFile[] | undefined, scenario: EnvelopeSigningScenario) {
	return files?.[0]?.signers?.find((signer) => {
		return signer.email === scenario.signerEmail || signer.displayName === scenario.signerName
	})
}

async function createEnvelopeWithVisibleSignatureRequirement(
	request: APIRequestContext,
	scenario: EnvelopeSigningScenario,
) {
	const pdfBase64 = await getSmallValidPdfBase64()

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
	const targetSigner = findSigner(envelope.files, scenario)

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
		{ interval: 250 },
	)
	const signLink = extractSignLink(email.Text)
	if (!signLink) {
		throw new Error('Sign link not found in email')
	}
	return signLink
}

async function openInvitationAsExternalSigner(page: Page, signLink: string) {
	await page.context().clearCookies()
	await page.goto('about:blank')
	await page.goto(signLink, { waitUntil: 'domcontentloaded' })

	const loginHeading = page.getByRole('heading', { name: 'Log in to Nextcloud' })
	if (await loginHeading.isVisible({ timeout: 1_500 }).catch(() => false)) {
		throw new Error(`Invitation link redirected to login instead of public sign page: ${page.url()}`)
	}
}

async function drawSignatureOnCanvas(signatureDialog: Locator, page: Page) {
	const canvas = signatureDialog.locator('canvas').first()
	await expect(canvas).toBeVisible()
	const box = await canvas.boundingBox()
	if (!box) {
		throw new Error('Signature canvas bounding box is not available')
	}

	const padding = 10
	const startX = box.x + Math.max(padding, box.width * 0.2)
	const endX = box.x + Math.min(box.width - padding, box.width * 0.8)
	const y = box.y + Math.min(box.height - padding, Math.max(padding, box.height * 0.5))

	await page.mouse.move(startX, y)
	await page.mouse.down()
	await page.mouse.move(endX, y)
	await page.mouse.up()
}

async function defineVisibleSignature(page: Page) {
	const openSignButton = page.locator('.button-wrapper').getByRole('button', { name: 'Sign document' })
	const defineSignatureButton = page.getByRole('button', { name: /Define your signature\.?/i })
	if (!await defineSignatureButton.isVisible().catch(() => false)) {
		if (await openSignButton.isVisible().catch(() => false)) {
			await openSignButton.click({ force: true })
		}
	}

	const deleteSignatureButton = page.getByRole('button', { name: 'Delete signature' })
	if (await deleteSignatureButton.isVisible().catch(() => false)) {
		await deleteSignatureButton.click()
	}

	await expect(defineSignatureButton).toBeVisible({ timeout: 15_000 })
	await defineSignatureButton.click()

	const signatureDialog = page.getByRole('dialog', { name: 'Customize your signatures' })
	await expect(signatureDialog).toBeVisible()
	await drawSignatureOnCanvas(signatureDialog, page)
	await signatureDialog.getByRole('button', { name: 'Save' }).click()

	const confirmDialog = page.getByLabel('Confirm your signature')
	await expect(confirmDialog).toBeVisible()
	await confirmDialog.getByRole('button', { name: 'Save' }).click()

	const signDocumentCta = page.locator('.button-wrapper').getByRole('button', { name: 'Sign document' })
	await expect(signDocumentCta).toBeVisible()
}

async function finishSigning(page: Page) {
	const openSignButton = page.locator('.button-wrapper').getByRole('button', { name: 'Sign document' })
	if (await openSignButton.isVisible().catch(() => false)) {
		await openSignButton.click({ force: true })
	}
	await page.getByRole('dialog', { name: 'Sign document' }).getByRole('button', { name: 'Sign document' }).click()
}

async function expectEnvelopeSigned(page: Page, envelopeName: string) {
	await page.waitForURL('**/validation/**', { waitUntil: 'commit' })
	await expect(page.getByText('Envelope information')).toBeVisible()
	await expect(page.getByText('Documents in this envelope')).toBeVisible()
	await expect(page.getByText('Congratulations you have digitally signed a document using LibreSign')).toBeVisible()
	await expect(page.locator('h2.app-sidebar-header__mainname')).toHaveText(envelopeName)
	await expect(page.getByText('You need to define a visible signature or initials to sign this document.')).not.toBeVisible()
}

test('unauthenticated signer can define a visible signature for an envelope with multiple PDFs', async ({ page }) => {
	const scenario = buildSigningScenario()

	await test.step('Given the system is configured to allow envelope signing via e-mail', async () => {
		await enableEnvelopeScenario(page.request)
	})

	await test.step('And an envelope with two PDFs is created requiring a visible signature on the first document', async () => {
		await createEnvelopeWithVisibleSignatureRequirement(page.request, scenario)
	})

	await test.step('When the external signer opens the invitation link received by e-mail', async () => {
		const publicSignLink = await waitForSignerInvitationLink(scenario.signerEmail)
		await openInvitationAsExternalSigner(page, publicSignLink)
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
