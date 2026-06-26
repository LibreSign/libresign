/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Bug reproduction: Validation screen doesn't display data correctly for envelopes with 2+ files
 */

import { expect, test } from '@playwright/test'
import type { APIRequestContext, Page } from '@playwright/test'

import { createMailpitClient, extractSignLink, waitForEmailTo } from '../support/mailpit'
import { configureOpenSsl, setSystemPolicy } from '../support/nc-provisioning'
import { getSmallValidPdfBase64 } from '../support/pdf-fixtures'

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

/**
 *
 */
function buildSigningScenario(): EnvelopeSigningScenario {
	const runId = Date.now()
	return {
		envelopeName: `Envelope Validation Bug - ${runId}`,
		signerEmail: `signer-validation-${runId}@libresign.coop`,
		signerName: 'Validation Tester',
	}
}

/**
 *
 * @param request
 * @param method
 * @param path
 * @param body
 */
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

/**
 *
 * @param request
 */
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
		JSON.stringify({
			can_create_account: false,
			factors: [
				{ name: 'account', enabled: false, requirement: 'optional' },
				{ name: 'email', enabled: true, requirement: 'required', signatureMethods: { clickToSign: { enabled: true }, emailToken: { enabled: false } } },
			],
		}),
	)
	await setSystemPolicy(request, 'make_validation_url_private', '0')
}

/**
 *
 * @param request
 * @param scenario
 */
async function createEnvelopeWithMultipleFiles(
	request: APIRequestContext,
	scenario: EnvelopeSigningScenario,
) {
	const pdfBase64 = await getSmallValidPdfBase64()

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

/**
 *
 * @param signerEmail
 */
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

/**
 *
 * @param page
 * @param signLink
 */
async function openInvitationAsExternalSigner(page: Page, signLink: string) {
	// API setup runs as admin. Clear cookies so the browser behaves like the real external signer.
	await page.context().clearCookies()
	await page.goto('about:blank')

	const baseCandidates = signLink.startsWith('/index.php/')
		? [signLink, signLink.replace(/^\/index\.php/, '')]
		: [signLink, `/index.php${signLink.startsWith('/') ? '' : '/'}${signLink}`]

	const signLinkCandidates = [...new Set(baseCandidates.flatMap((candidate) => {
		const withoutPdfSuffix = candidate.replace(/\/pdf(?=$|[?#])/, '')
		return candidate === withoutPdfSuffix ? [candidate] : [candidate, withoutPdfSuffix]
	}))]

	for (const candidate of signLinkCandidates) {
		try {
			await page.goto(candidate, { waitUntil: 'commit', timeout: 15_000 })
		} catch {
			continue
		}

		const currentUrl = page.url()
		if (currentUrl.includes('/login') || /\/p\/error(?:$|[/?#])/.test(currentUrl)) {
			continue
		}

		if (currentUrl.includes('/apps/libresign/p/sign/')) {
			return
		}
	}

	throw new Error(`Invitation link redirected to login instead of public sign page: ${page.url()}`)
}

/**
 *
 * @param page
 */
async function defineClickToSignature(page: Page) {
	// Wait for click-to-sign button
	await expect(page.locator('.button-wrapper').getByRole('button', { name: 'Sign document' })).toBeVisible({ timeout: 15_000 })
}

/**
 *
 * @param page
 */
async function finishSigning(page: Page) {
	const signButton = page.locator('.button-wrapper').getByRole('button', { name: 'Sign document' })
	await expect(signButton).toBeVisible({ timeout: 15_000 })
	await signButton.click({ force: true })
	const confirmSignButton = page.getByRole('dialog', { name: 'Sign document' }).getByRole('button', { name: 'Sign document' })
	await expect(confirmSignButton).toBeVisible({ timeout: 15_000 })
	await confirmSignButton.click()
}

test('validation screen should display all data correctly for envelope with 2 files', async ({ page }) => {
	const scenario = buildSigningScenario()

	await test.step('Given the system is configured to allow envelope signing via e-mail', async () => {
		await enableEnvelopeScenario(page.request)
	})

	await test.step('And an envelope with two files is created', async () => {
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
		const envelopeInformationSection = page.locator('.section').filter({
			has: page.getByRole('heading', { name: 'Envelope information' }),
		}).first()
		await expect(envelopeInformationSection).toBeVisible()

		// Verify envelope name is displayed
		await expect(envelopeInformationSection.getByText(scenario.envelopeName)).toBeVisible()

		// Verify documents in envelope section exists
		await expect(page.getByText('Documents in this envelope')).toBeVisible()

		await expect(page.getByText('Number of documents:')).toBeVisible()

		// Get the documents list
		const documentsList = page.locator('ul.documents-list li.document-item')
		const documentsCount = await documentsList.count()
		expect(documentsCount).toBe(2)

		// Success message should be visible
		await expect(page.getByText('Congratulations you have digitally signed a document using LibreSign')).toBeVisible()
	})
})
