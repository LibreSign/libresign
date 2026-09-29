/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import path from 'node:path'

import { expect, test } from '@playwright/test'

import { login } from '../support/nc-login'
import { configureOpenSsl, resetUserSigningCertificate, setSystemPolicy } from '../support/nc-provisioning'
import { clickAddSigner, selectAccountSigner } from '../support/request-signature'
import { clickSignDocumentButton } from '../support/sign-flow'
import {
	createAuthenticatedRequestContext,
	getEffectivePolicy,
	getSystemPolicySnapshot,
	policyRequest,
	restoreSystemPolicySnapshot,
	setSystemPolicyEntry,
	type SystemPolicySnapshot,
} from '../support/policy-api'
import { useFooterPolicyGuard, useRequestSignPolicyGuard } from '../support/system-policies'

useFooterPolicyGuard()
useRequestSignPolicyGuard()

const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
const DEVICE_POLICY = 'signer_device_geolocation'
const IP_POLICY = 'signer_ip_geolocation'
const IDENTIFY_POLICY = 'identify_methods'
const GEOIP_TEST_DB = process.env.PLAYWRIGHT_GEOIP_PATH
	?? path.resolve(process.cwd(), 'tests/php/fixtures/geoip/GeoIP2-City-Test.mmdb')

let adminContext: Awaited<ReturnType<typeof createAuthenticatedRequestContext>> | null = null
let originalDevice: SystemPolicySnapshot | null = null
let originalIp: SystemPolicySnapshot | null = null
let originalIdentify: SystemPolicySnapshot | null = null
let originalGeoIpPath: string | null = null

test.describe.configure({ mode: 'serial', retries: 0, timeout: 180000 })

test.use({
	geolocation: { latitude: -23.5505, longitude: -46.6333 },
	permissions: ['geolocation'],
})

test.afterEach(async () => {
	if (!adminContext) {
		return
	}

	if (originalDevice) {
		await restoreSystemPolicySnapshot(adminContext, DEVICE_POLICY, originalDevice)
	}
	if (originalIp) {
		await restoreSystemPolicySnapshot(adminContext, IP_POLICY, originalIp)
	}
	if (originalIdentify) {
		await restoreSystemPolicySnapshot(adminContext, IDENTIFY_POLICY, originalIdentify)
	}
	if (originalGeoIpPath !== null) {
		await policyRequest(adminContext, 'POST', '/apps/libresign/api/v1/admin/geoip', {
			path: originalGeoIpPath,
		})
	}
	await adminContext.dispose()
	adminContext = null
	originalDevice = null
	originalIp = null
	originalIdentify = null
	originalGeoIpPath = null
})

test('request, sign, and validate device plus IP geolocation from a frozen snapshot', async ({ page }) => {
	test.slow()

	await page.addInitScript(() => {
		const coords = {
			latitude: -23.5505,
			longitude: -46.6333,
			accuracy: 15,
			altitude: null,
			altitudeAccuracy: null,
			heading: null,
			speed: null,
		}
		Object.defineProperty(navigator, 'geolocation', {
			configurable: true,
			value: {
				getCurrentPosition(success: PositionCallback) {
					success({ coords, timestamp: Date.now() } as GeolocationPosition)
				},
				watchPosition() {
					return 0
				},
				clearWatch() {},
			},
		})
	})

	adminContext = await createAuthenticatedRequestContext(adminUser, adminPassword)
	originalDevice = await getSystemPolicySnapshot(adminContext, DEVICE_POLICY)
	originalIp = await getSystemPolicySnapshot(adminContext, IP_POLICY)
	originalIdentify = await getSystemPolicySnapshot(adminContext, IDENTIFY_POLICY)
	const originalGeoIp = await policyRequest(adminContext, 'GET', '/apps/libresign/api/v1/admin/geoip')
	originalGeoIpPath = typeof originalGeoIp.data.path === 'string' ? originalGeoIp.data.path : ''

	await setSystemPolicyEntry(adminContext, DEVICE_POLICY, { mode: 'optional' }, true)
	await setSystemPolicyEntry(adminContext, IP_POLICY, { mode: 'enabled' }, true)
	await policyRequest(adminContext, 'POST', '/apps/libresign/api/v1/admin/geoip', {
		path: GEOIP_TEST_DB,
	})

	const device = await getEffectivePolicy(adminContext, DEVICE_POLICY)
	const ip = await getEffectivePolicy(adminContext, IP_POLICY)
	expect(device?.effectiveValue).toEqual({ mode: 'optional' })
	expect(ip?.effectiveValue).toEqual({ mode: 'enabled' })

	await login(page.request, adminUser, adminPassword)
	await resetUserSigningCertificate(page.request, adminUser, adminPassword)
	await configureOpenSsl(page.request, 'LibreSign Test', {
		C: 'BR',
		OU: ['Organization Unit'],
		ST: 'Rio de Janeiro',
		O: 'LibreSign',
		L: 'Rio de Janeiro',
	})
	await setSystemPolicy(
		page.request,
		IDENTIFY_POLICY,
		JSON.stringify({
			factors: [
				{ name: 'account', enabled: true, requirement: 'required', signatureMethods: { clickToSign: { enabled: true } } },
				{ name: 'email', enabled: false, requirement: 'optional' },
			],
		}),
	)

	// Re-assert immediately before upload so the write-once file snapshot
	// captures optional/enabled rather than a stale disabled baseline.
	await setSystemPolicyEntry(adminContext, DEVICE_POLICY, { mode: 'optional' }, true)
	await setSystemPolicyEntry(adminContext, IP_POLICY, { mode: 'enabled' }, true)
	expect((await getEffectivePolicy(adminContext, DEVICE_POLICY))?.effectiveValue).toEqual({ mode: 'optional' })
	expect((await getEffectivePolicy(adminContext, IP_POLICY))?.effectiveValue).toEqual({ mode: 'enabled' })

	await page.goto('./apps/libresign')
	await page.getByRole('button', { name: 'Upload from URL' }).click()
	await page.getByRole('textbox', { name: 'URL of a PDF file' }).fill('https://raw.githubusercontent.com/LibreSign/libresign/main/tests/php/fixtures/pdfs/small_valid.pdf')
	const uploadResponsePromise = page.waitForResponse((response) =>
		response.request().method() === 'POST'
		&& response.url().includes('/apps/libresign/api/v1/file')
		&& response.ok(),
	)
	await page.getByRole('button', { name: 'Send' }).click()
	const uploadBody = await (await uploadResponsePromise).json() as {
		ocs?: { data?: { metadata?: { policy_snapshot?: Record<string, { effectiveValue?: { mode?: string } }> } } }
	}
	expect(uploadBody.ocs?.data?.metadata?.policy_snapshot?.signer_device_geolocation?.effectiveValue).toEqual({ mode: 'optional' })
	expect(uploadBody.ocs?.data?.metadata?.policy_snapshot?.signer_ip_geolocation?.effectiveValue).toEqual({ mode: 'enabled' })

	await clickAddSigner(page)
	await selectAccountSigner(page, 'a', /admin/i)

	const signerDialog = page.getByRole('dialog', { name: /Add new signer/i }).last()
	const deviceToggle = signerDialog.locator('.checkbox-radio-switch').filter({
		hasText: 'Require device-reported location to sign',
	})
	await expect(deviceToggle).toBeVisible()
	await expect(signerDialog.getByText(/IP-based|source IP|approximate location/i)).toHaveCount(0)
	await deviceToggle.locator('.checkbox-radio-switch__content').click()
	await expect(deviceToggle.getByRole('switch')).toBeChecked()

	const saveSignerResponsePromise = page.waitForResponse((response) =>
		['POST', 'PATCH'].includes(response.request().method())
		&& response.url().includes('/apps/libresign/api/v1/request-signature'),
	)
	await signerDialog.getByRole('button', { name: 'Save' }).click()
	const saveSignerRequest = (await saveSignerResponsePromise).request()
	const saveSignerPayload = saveSignerRequest.postDataJSON() as { signers?: Array<{ deviceGeolocationRequired?: boolean }> }
	expect(saveSignerPayload.signers?.[0]?.deviceGeolocationRequired).toBe(true)

	await page.getByRole('button', { name: 'Request signatures' }).click()
	await page.getByRole('button', { name: 'Send' }).click()

	await setSystemPolicyEntry(adminContext, DEVICE_POLICY, { mode: 'disabled' }, true)
	await clickAddSigner(page)
	await selectAccountSigner(page, 'a', /admin/i)
	await expect(page.getByRole('dialog', { name: /Add new signer/i }).last()
		.locator('.checkbox-radio-switch')
		.filter({ hasText: 'Require device-reported location to sign' })).toBeVisible()
	await page.getByRole('dialog', { name: /Add new signer/i }).last().getByRole('button', { name: 'Cancel' }).click()

	const signDetailResponsePromise = page.waitForResponse((response) =>
		response.request().method() === 'GET'
		&& response.url().includes('/apps/libresign/api/v1/file/validate/uuid/')
		&& response.ok(),
	{ timeout: 30_000 })
	await page.getByRole('button', { name: 'Sign document' }).first().click()
	await page.waitForURL('**/f/sign/**/pdf')
	await signDetailResponsePromise
	await expect(page.getByLabel('PDF document to sign')).toBeVisible({ timeout: 15_000 })
	await expect(page.getByText('Device-reported location is required to sign this document.')).toBeVisible({ timeout: 15_000 })

	const signResponsePromise = page.waitForResponse((response) =>
		response.request().method() === 'POST'
		&& response.url().includes('/apps/libresign/api/v1/sign/'),
	{ timeout: 30_000 })
	await clickSignDocumentButton(page)

	const confirmSign = page.getByRole('dialog', { name: 'Sign document' }).getByRole('button', { name: 'Sign document' })
	await expect(confirmSign).toBeVisible({ timeout: 15_000 })
	await confirmSign.click()

	const privacyDialog = page.getByRole('dialog', { name: 'Device-reported location required' })
	await expect(privacyDialog).toBeVisible({ timeout: 10_000 })
	await privacyDialog.getByRole('button', { name: 'Continue' }).click()

	const signResponse = await signResponsePromise
	expect(signResponse.ok(), `Sign API failed with status ${signResponse.status()}`).toBeTruthy()

	const signBody = await signResponse.request().postDataJSON() as Record<string, unknown>
	expect(signBody).toHaveProperty('deviceGeolocation')
	expect(JSON.stringify(signBody)).not.toContain('sourceIp')
	expect(signBody).not.toHaveProperty('geolocation')

	await expect(page.getByText('This document is valid')).toBeVisible()
	await page.getByRole('button', { name: 'Expand details' }).click()
	await expect(page.getByText('Signer geolocation')).toBeVisible()
	await expect(page.getByText('Device-reported location')).toBeVisible()
	await expect(page.getByText('IP-based approximate location')).toBeVisible()
	await page.getByRole('button', { name: 'Expand device-reported location details', exact: true }).click()
	await expect(page.getByText('-23.5505')).toBeVisible()
})
