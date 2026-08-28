/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test, type Page } from '@playwright/test'

import { login } from '../support/nc-login'
import { configureOpenSsl, getSystemPolicyValue, setCertificateEngine, setSystemPolicy } from '../support/nc-provisioning'
import { getSmallValidPdfBase64 } from '../support/pdf-fixtures'
import { useFooterPolicyGuard, useRequestSignPolicyGuard } from '../support/system-policies'

useFooterPolicyGuard()
useRequestSignPolicyGuard()

const GEOLOCATION_POLICY_KEY = 'signer_geolocation'
const GEOLOCATION_DEFAULT = JSON.stringify({ mode: 'disabled', allowRequesterOverride: false })

test.setTimeout(120_000)

type SignerRecord = {
	me?: boolean
	sign_request_uuid?: string
	metadata?: {
		geolocationRequirement?: string
		geolocation?: Record<string, unknown>
	}
}

type DetailedFileResponse = {
	uuid?: string
	signers?: SignerRecord[]
}

async function requestLibreSignApiAsAdmin(
	request: Page['request'],
	method: 'GET' | 'POST' | 'PATCH',
	path: string,
	body?: Record<string, unknown>,
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
		data: body ? JSON.stringify(body) : undefined,
		failOnStatusCode: false,
	})

	return {
		status: response.status(),
		body: await response.text(),
		json: async () => response.json() as Promise<{ ocs: { data: DetailedFileResponse } }>,
		ok: response.ok(),
	}
}

async function createSelfSignRequest(
	request: Page['request'],
	fileName: string,
	userId: string,
	geolocationRequired = false,
) {
	const pdfBase64 = await getSmallValidPdfBase64()
	const result = await requestLibreSignApiAsAdmin(request, 'POST', '/request-signature', {
		name: fileName,
		status: 1,
		file: {
			name: fileName,
			base64: pdfBase64,
		},
		signers: [{
			displayName: userId,
			geolocationRequired,
			identifyMethods: [{
				method: 'account',
				value: userId,
				mandatory: 1,
			}],
		}],
	})

	expect(result.ok, `Create request failed: ${result.status} ${result.body}`).toBeTruthy()
	const payload = await result.json()
	const signRequestUuid = payload.ocs.data.signers?.find((signer) => signer.me)?.sign_request_uuid
	if (!signRequestUuid) {
		throw new Error('sign_request_uuid not found')
	}

	return {
		fileUuid: payload.ocs.data.uuid ?? '',
		signRequestUuid,
		signer: payload.ocs.data.signers?.find((signer) => signer.me),
	}
}

async function signWithGeolocation(
	request: Page['request'],
	signRequestUuid: string,
	geolocation?: Record<string, unknown>,
) {
	return requestLibreSignApiAsAdmin(request, 'POST', `/sign/uuid/${signRequestUuid}`, {
		method: 'clickToSign',
		...(geolocation ? { geolocation } : {}),
	})
}

test.describe('signer geolocation backend (#6960)', () => {
	let previousGeolocationPolicy: string | null = null

	test.beforeEach(async ({ page }) => {
		await login(
			page.request,
			process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
			process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
		)
		previousGeolocationPolicy = await getSystemPolicyValue(page.request, GEOLOCATION_POLICY_KEY)
	})

	test.afterEach(async ({ page }) => {
		if (previousGeolocationPolicy === null) {
			return
		}
		await setSystemPolicy(page.request, GEOLOCATION_POLICY_KEY, previousGeolocationPolicy)
	})

	test('API rejects required geolocation when missing and accepts valid payload', async ({ page }) => {
		const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'

		await configureOpenSsl(page.request, 'LibreSign Test', {
			C: 'BR',
			OU: ['Organization Unit'],
			ST: 'Rio de Janeiro',
			O: 'LibreSign',
			L: 'Rio de Janeiro',
		})
		await setCertificateEngine(page.request, 'openssl')
		await setSystemPolicy(
			page.request,
			'identify_methods',
			JSON.stringify({
				factors: [
					{ name: 'account', enabled: true, requirement: 'required', signatureMethods: { clickToSign: { enabled: true } } },
					{ name: 'email', enabled: false, requirement: 'optional' },
				],
			}),
		)
		await setSystemPolicy(
			page.request,
			'identification_documents',
			JSON.stringify({ enabled: false, approvers: ['admin'] }),
		)
		await setSystemPolicy(
			page.request,
			GEOLOCATION_POLICY_KEY,
			JSON.stringify({ mode: 'required', allowRequesterOverride: false }),
		)

		const uniqueName = `geo-required-${Date.now()}.pdf`
		const { signRequestUuid, signer } = await createSelfSignRequest(page.request, uniqueName, adminUser)
		expect(signer?.metadata?.geolocationRequirement).toBe('required')

		const missingGeo = await signWithGeolocation(page.request, signRequestUuid)
		expect(missingGeo.status, missingGeo.body).toBe(422)

		const collectedGeo = await signWithGeolocation(page.request, signRequestUuid, {
			status: 'collected',
			latitude: -23.5505,
			longitude: -46.6333,
			accuracy: 25,
			timestamp: Date.now(),
		})
		expect(collectedGeo.ok, collectedGeo.body).toBeTruthy()
	})

	test('browser sign fails without geolocation when policy is required', async ({ page }) => {
		const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'

		await configureOpenSsl(page.request, 'LibreSign Test', {
			C: 'BR',
			OU: ['Organization Unit'],
			ST: 'Rio de Janeiro',
			O: 'LibreSign',
			L: 'Rio de Janeiro',
		})
		await setCertificateEngine(page.request, 'openssl')
		await setSystemPolicy(
			page.request,
			'identify_methods',
			JSON.stringify({
				factors: [
					{ name: 'account', enabled: true, requirement: 'required', signatureMethods: { clickToSign: { enabled: true } } },
					{ name: 'email', enabled: false, requirement: 'optional' },
				],
			}),
		)
		await setSystemPolicy(
			page.request,
			'identification_documents',
			JSON.stringify({ enabled: false, approvers: ['admin'] }),
		)
		await setSystemPolicy(
			page.request,
			GEOLOCATION_POLICY_KEY,
			JSON.stringify({ mode: 'required', allowRequesterOverride: false }),
		)

		const uniqueName = `geo-browser-${Date.now()}.pdf`
		const { signRequestUuid } = await createSelfSignRequest(page.request, uniqueName, adminUser)

		await page.goto(`./apps/libresign/f/sign/${signRequestUuid}/pdf`)
		await expect(page.getByText(/Ready to sign|Pronto para assinar/i)).toBeVisible({ timeout: 15_000 })

		const signButton = page.locator('.sign-pdf-sidebar .button-wrapper').getByRole('button', { name: /Sign document|Assinar documento/i })
		await expect(signButton).toBeVisible({ timeout: 15_000 })
		await signButton.click({ force: true })

		const signResponsePromise = page.waitForResponse((response) =>
			response.request().method() === 'POST'
			&& response.url().includes('/apps/libresign/api/v1/sign/'),
		)
		await page.getByRole('dialog').getByRole('button', { name: /Sign document|Assinar documento/i }).click()
		const signResponse = await signResponsePromise

		expect(signResponse.status(), await signResponse.text()).toBe(422)
		await expect(page.locator('.sign-pdf-sidebar').getByText(/Geolocation is required to sign this document/i)).toBeVisible({ timeout: 10_000 })
	})
})
