/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'

import { login } from '../support/nc-login'
import {
	createAuthenticatedRequestContext,
	getEffectivePolicy,
	getSystemPolicySnapshot,
	policyRequest,
	restoreSystemPolicySnapshot,
	setSystemPolicyEntry,
	type SystemPolicySnapshot,
} from '../support/policy-api'

const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

const DEVICE_POLICY = 'signer_device_geolocation'
const IP_POLICY = 'signer_ip_geolocation'

let adminContext: Awaited<ReturnType<typeof createAuthenticatedRequestContext>> | null = null
let originalDevice: SystemPolicySnapshot | null = null
let originalIp: SystemPolicySnapshot | null = null

test.describe.configure({ mode: 'serial', retries: 0, timeout: 90000 })

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
	await adminContext.dispose()
	adminContext = null
	originalDevice = null
	originalIp = null
})

test('Policy Workbench groups device and IP geolocation as independent cards', async ({ page }) => {
	adminContext = await createAuthenticatedRequestContext(adminUser, adminPassword)
	originalDevice = await getSystemPolicySnapshot(adminContext, DEVICE_POLICY)
	originalIp = await getSystemPolicySnapshot(adminContext, IP_POLICY)

	await setSystemPolicyEntry(adminContext, DEVICE_POLICY, JSON.stringify({ mode: 'optional' }), true)
	await setSystemPolicyEntry(adminContext, IP_POLICY, JSON.stringify({ mode: 'enabled' }), true)

	const device = await getEffectivePolicy(adminContext, DEVICE_POLICY)
	const ip = await getEffectivePolicy(adminContext, IP_POLICY)
	expect(device?.effectiveValue).toEqual({ mode: 'optional' })
	expect(ip?.effectiveValue).toEqual({ mode: 'enabled' })

	await login(page.request, adminUser, adminPassword)
	await page.goto('./settings/admin/libresign')

	const recordedSection = page.locator('[data-category-key="what-gets-recorded"]')
	await expect(recordedSection).toBeVisible()
	await expect(recordedSection.getByRole('heading', { name: 'Device-reported location' })).toBeVisible()
	await expect(recordedSection.getByRole('heading', { name: 'IP-based approximate location' })).toBeVisible()
	await expect(page.getByRole('heading', { name: 'GeoIP database' })).toBeVisible()
})

test('GeoIP admin settings save, replace, and clear a path without exposing signer data', async ({ page }) => {
	adminContext = await createAuthenticatedRequestContext(adminUser, adminPassword)

	const original = await policyRequest(adminContext, 'GET', '/apps/libresign/api/v1/admin/geoip')
	expect(original.httpStatus).toBe(200)

	await login(page.request, adminUser, adminPassword)
	await page.goto('./settings/admin/libresign')

	const pathInput = page.getByLabel('Database path')
	await expect(pathInput).toBeVisible()
	const geoIpSection = page.getByRole('heading', { name: 'GeoIP database' }).locator('xpath=ancestor::section[1]')
	await pathInput.fill('/tmp/libresign-missing-geoip.mmdb')
	await geoIpSection.getByRole('button', { name: 'Save', exact: true }).click()
	await expect(geoIpSection.getByText('Database file not found').or(page.getByText('GeoIP database path saved'))).toBeVisible()
	await expect(geoIpSection.getByText('does not prevent signatures').or(geoIpSection.getByText('This does not prevent signatures'))).toBeVisible()
	await expect(geoIpSection).not.toContainText('sourceIp')

	await geoIpSection.getByRole('button', { name: 'Clear path' }).click()
	await expect(pathInput).toHaveValue('')

	if (typeof original.data.path === 'string') {
		await policyRequest(adminContext, 'POST', '/apps/libresign/api/v1/admin/geoip', { path: original.data.path })
	} else {
		await policyRequest(adminContext, 'POST', '/apps/libresign/api/v1/admin/geoip', { path: '' })
	}
})
