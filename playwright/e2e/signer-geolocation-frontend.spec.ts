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
let originalGeoIpPath: string | null = null

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
	if (originalGeoIpPath !== null) {
		await policyRequest(adminContext, 'POST', '/apps/libresign/api/v1/admin/geoip', {
			path: originalGeoIpPath,
		})
	}
	await adminContext.dispose()
	adminContext = null
	originalDevice = null
	originalIp = null
	originalGeoIpPath = null
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

	const geolocationSection = page.locator('[data-category-key="signer-geolocation"]')
	await expect(geolocationSection.getByText('Signer geolocation', { exact: true })).toBeVisible()
	await expect(geolocationSection.getByRole('heading', { name: 'Device-reported location' })).toBeVisible()
	await expect(geolocationSection.getByRole('heading', { name: 'IP-based approximate location' })).toBeVisible()
	await expect(page.getByRole('heading', { name: 'GeoIP database' })).toHaveCount(0)
})

async function openIpGeolocationSystemEditor(page: import('@playwright/test').Page) {
	const geolocationSection = page.locator('[data-category-key="signer-geolocation"]')
	const ipCard = geolocationSection.locator('article').filter({
		has: page.getByRole('heading', { name: 'IP-based approximate location' }),
	})
	await ipCard.getByRole('button', { name: 'Configure setting' }).click()

	const settingDialog = page.getByRole('dialog', { name: 'IP-based approximate location' })
	await expect(settingDialog).toBeVisible()
	await settingDialog.getByRole('button', { name: 'Change', exact: true }).click()

	const editorDialog = page.getByRole('dialog').filter({
		has: page.locator('[data-cy="geoip-database-dependency"]'),
	}).last()
	await expect(editorDialog.locator('[data-cy="geoip-database-dependency"]')).toBeVisible()
	return editorDialog
}

test('GeoIP admin settings save, replace, and clear a path without exposing signer data', async ({ page }) => {
	adminContext = await createAuthenticatedRequestContext(adminUser, adminPassword)

	const original = await policyRequest(adminContext, 'GET', '/apps/libresign/api/v1/admin/geoip')
	expect(original.httpStatus).toBe(200)
	originalGeoIpPath = typeof original.data.path === 'string' ? original.data.path : ''

	await login(page.request, adminUser, adminPassword)
	await page.goto('./settings/admin/libresign')

	const editorDialog = await openIpGeolocationSystemEditor(page)
	await editorDialog.getByRole('button', { name: /Configure( database)?/ }).click()

	const geoIpDialog = page.getByRole('dialog', { name: 'GeoIP database' })
	await expect(geoIpDialog).toBeVisible()
	const pathInput = geoIpDialog.getByLabel('Database path')
	await expect(pathInput).toBeVisible()
	await pathInput.fill('/tmp/libresign-missing-geoip.mmdb')
	await geoIpDialog.getByRole('button', { name: 'Save', exact: true }).click()
	await expect(geoIpDialog.getByText('Database file not found')).toBeVisible()
	await expect(geoIpDialog.getByText('This does not prevent signatures')).toBeVisible()
	await expect(geoIpDialog).not.toContainText('sourceIp')

	await geoIpDialog.getByRole('button', { name: 'Clear path' }).click()
	await expect(pathInput).toHaveValue('')
})
