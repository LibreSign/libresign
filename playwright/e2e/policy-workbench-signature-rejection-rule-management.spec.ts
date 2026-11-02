/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test, type Locator } from '@playwright/test'

import { bootstrapLibreSignAdmin, ensureCatalogSettingCardVisible } from '../support/footer-policy-workbench'
import {
	createAuthenticatedRequestContext,
	getEffectivePolicy,
	getSystemPolicySnapshot,
	restoreSystemPolicySnapshot,
	type SystemPolicySnapshot,
} from '../support/policy-api'
import { openPolicyWorkbenchSystemRuleEditor, waitForPolicyWorkbenchIdle } from '../support/policy-workbench-rules'

const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

const REJECTION_KEYS = [
	'rejection_enabled',
	'rejection_behavior',
	'rejection_comment_mode',
	'rejection_visibility',
	'rejection_comment_visibility',
] as const

const expectedValues = {
	rejection_enabled: true,
	rejection_behavior: 'continue',
	rejection_comment_mode: 'required',
	rejection_visibility: 'participants',
	rejection_comment_visibility: 'requester',
}

const selections: Array<{ group: string, option: string }> = [
	{ group: 'After a rejection', option: 'Let the others keep signing' },
	{ group: 'Rejection comment', option: 'Required comment' },
	{ group: 'Who can see the rejection', option: 'Requester and signers' },
	{ group: 'Who can read the comment', option: 'Requester only' },
]

test.describe.configure({ mode: 'serial', retries: 0, timeout: 120000 })

async function openSignatureRejectionEditor(page: import('@playwright/test').Page): Promise<Locator> {
	const card = await ensureCatalogSettingCardVisible(page, /Signature rejection/i, 'rejection')
	await card.click()

	const dialog = page.getByRole('dialog').filter({ hasText: /Signature rejection/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10000 })
	await page.getByText(/Loading rules/i).waitFor({ state: 'hidden', timeout: 20000 }).catch(() => {})

	return openPolicyWorkbenchSystemRuleEditor(dialog)
}

test('signature rejection restores the five values of a system rule after reload', async ({ page }) => {
	const adminRequest = await createAuthenticatedRequestContext(adminUser, adminPassword)
	const snapshots = new Map<string, SystemPolicySnapshot>()
	for (const key of REJECTION_KEYS) {
		snapshots.set(key, await getSystemPolicySnapshot(adminRequest, key))
	}

	try {
		await bootstrapLibreSignAdmin(page)
		await page.goto('./settings/admin/libresign', { waitUntil: 'domcontentloaded' })
		await waitForPolicyWorkbenchIdle(page)

		const ruleDialog = await openSignatureRejectionEditor(page)
		const enabledSwitch = ruleDialog.getByLabel('Allow signers to reject')
		if (!(await enabledSwitch.isChecked())) {
			await ruleDialog.getByText('Allow signers to reject', { exact: true }).click()
		}
		for (const { group, option } of selections) {
			await ruleDialog.getByRole('group', { name: group }).getByText(option, { exact: true }).click()
		}

		const saveButton = ruleDialog.getByRole('button', { name: /Create rule|Save changes/i }).first()
		await expect(saveButton).toBeEnabled({ timeout: 10000 })
		const [response] = await Promise.all([
			page.waitForResponse((response) => response.request().method() === 'POST'
				&& response.url().includes('/apps/libresign/api/v1/policies/compound/system/rejection_enabled')),
			saveButton.click(),
		])
		expect(response.status()).toBe(200)
		expect((response.request().postDataJSON() as { values?: Record<string, unknown> }).values).toEqual(expectedValues)

		await waitForPolicyWorkbenchIdle(page)
		await page.reload()
		await waitForPolicyWorkbenchIdle(page)

		const reopenedDialog = await openSignatureRejectionEditor(page)
		await expect(reopenedDialog.getByLabel('Allow signers to reject')).toBeChecked()
		for (const { group, option } of selections) {
			await expect(reopenedDialog.getByRole('group', { name: group }).getByLabel(option, { exact: true })).toBeChecked()
		}

		for (const key of REJECTION_KEYS) {
			const policy = await getEffectivePolicy(adminRequest, key)
			expect(policy?.effectiveValue, key).toBe(expectedValues[key])
		}
	} finally {
		for (const key of REJECTION_KEYS) {
			await restoreSystemPolicySnapshot(adminRequest, key, snapshots.get(key)!)
		}
		await adminRequest.dispose()
	}
})
