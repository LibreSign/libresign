/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect, type APIRequestContext, type Locator, type Page } from '@playwright/test'
import { login } from '../support/nc-login'
import { expandSettingsMenu } from '../support/nc-navigation'
import {
	configureOpenSsl,
	ensureGroupExists,
	ensureUserExists,
	ensureUserInGroup,
} from '../support/nc-provisioning'
import {
	clearUserPolicyPreference,
	createAuthenticatedRequestContext,
	getEffectivePolicy,
	policyRequest,
	setGroupPolicyEntry,
	setSystemPolicyEntry,
} from '../support/policy-api'

type SystemPolicySnapshot = {
	exists: boolean
	value: unknown
	allowChildOverride: boolean
}

const adminUser = 'admin'
const adminPass = process.env.ADMIN_PASSWORD || 'admin'

test.describe('Policy preferences: boolean settings', () => {
	test('user can save and clear collect_metadata, docmdp and signature_text preferences', async ({ page }) => {
		test.setTimeout(180000)
		const groupId = `pref-boolean-${Date.now()}`
		const endUser = `prefboolean_${Date.now()}`
		const endPass = 'user1234'

		const adminCtx = await createAuthenticatedRequestContext(adminUser, adminPass)
		let endUserCtx: APIRequestContext | null = null
		const originalGroupsRequestSign = await getSystemPolicySnapshot(adminCtx, 'groups_request_sign')
		const originalCollectMetadata = await getSystemPolicySnapshot(adminCtx, 'collect_metadata')
		const originalDocmdp = await getSystemPolicySnapshot(adminCtx, 'docmdp')
		const originalSignatureText = await getSystemPolicySnapshot(adminCtx, 'signature_stamp')
		const signatureTextSystemValue = JSON.stringify({
			template: 'System template',
			template_font_size: 9,
			signature_font_size: 9,
			signature_width: 90,
			signature_height: 60,
			render_mode: 'default',
		})
		const signatureTextGroupValue = JSON.stringify({
			template: 'Group template',
			template_font_size: 10,
			signature_font_size: 10,
			signature_width: 110,
			signature_height: 70,
			render_mode: 'text',
		})

		try {
			await login(page.request, adminUser, adminPass)
			await configureOpenSsl(page.request, 'LibreSign Test', {
				C: 'BR',
				OU: ['Organization Unit'],
				ST: 'Rio de Janeiro',
				O: 'LibreSign',
				L: 'Rio de Janeiro',
			})

			await ensureGroupExists(page.request, groupId)
			await ensureUserExists(page.request, endUser, endPass)
			await ensureUserInGroup(page.request, endUser, groupId)

			await setSystemPolicyEntry(adminCtx, 'groups_request_sign', JSON.stringify([groupId]), true)
			await setSystemPolicyEntry(adminCtx, 'collect_metadata', JSON.stringify(false), true)
			await setGroupPolicyEntry(adminCtx, groupId, 'collect_metadata', JSON.stringify(true), true)
			await setSystemNumericPolicyEntry(adminCtx, 'docmdp', 0, true)
			await setGroupNumericPolicyEntry(adminCtx, groupId, 'docmdp', 2, true)
			await setSystemPolicyEntry(adminCtx, 'signature_stamp', signatureTextSystemValue, true)
			await setGroupPolicyEntry(adminCtx, groupId, 'signature_stamp', signatureTextGroupValue, true)

			endUserCtx = await createAuthenticatedRequestContext(endUser, endPass)

			await login(page.request, endUser, endPass)
			await page.goto('/index.php/apps/libresign/f/preferences')
			await page.locator('#app-navigation-vue').waitFor({ state: 'visible' })
			await expandSettingsMenu(page)

			const collectMetadataSection = await sectionByTitle(page, 'Collect signer metadata')
			const docMdpSection = await sectionByTitle(page, 'PDF certification')
			const signatureTextSection = await sectionByTitle(page, /Signature stamp text|Signature text|Signature stamp/i)

			expect(await collectMetadataSection.isVisible()).toBe(true)
			expect(await docMdpSection.isVisible()).toBe(true)
			expect(await signatureTextSection.isVisible()).toBe(true)

			await savePreferenceAsDisabled(collectMetadataSection)
			await saveDocMdpPreference(docMdpSection, 3)
			await saveSignatureTextTemplatePreference(signatureTextSection, 'User custom template')

			await expectPolicyEffectiveValue(endUserCtx, 'collect_metadata', false, 'user')
			await expectDocMdpEffectiveValue(endUserCtx, 3, 'user')
			await expectSignatureTextEffectiveScope(endUserCtx, 'user', 'User custom template')

			await clearPreference(collectMetadataSection)
			await clearPreference(docMdpSection)
			await clearPreference(signatureTextSection)

			await expectPolicyEffectiveValue(endUserCtx, 'collect_metadata', true, 'group')
			await expectDocMdpEffectiveValue(endUserCtx, 2, 'group')
			await expectSignatureTextEffectiveScope(endUserCtx, 'group', 'Group template')
		} finally {
			if (endUserCtx) {
				await clearUserPolicyPreference(endUserCtx, 'collect_metadata', [200, 401, 500])
				await clearUserPolicyPreference(endUserCtx, 'docmdp', [200, 401, 500])
				await clearUserPolicyPreference(endUserCtx, 'signature_stamp', [200, 401, 405, 500])
				await endUserCtx.dispose()
			}

			await restoreSystemPolicySnapshot(adminCtx, 'groups_request_sign', originalGroupsRequestSign)
			await restoreSystemPolicySnapshot(adminCtx, 'collect_metadata', originalCollectMetadata)
			await restoreSystemPolicySnapshot(adminCtx, 'docmdp', originalDocmdp)
			await restoreSystemPolicySnapshot(adminCtx, 'signature_stamp', originalSignatureText)

			await policyRequest(adminCtx, 'DELETE', `/cloud/users/${endUser}`)
			await policyRequest(adminCtx, 'DELETE', `/cloud/groups/${groupId}`)
			await adminCtx.dispose()
		}
	})
})

async function sectionByTitle(page: Page, title: string | RegExp): Promise<Locator> {
	const heading = page.getByRole('heading', { name: title }).first()
	await expect(heading).toBeVisible()
	const section = heading.locator('xpath=ancestor::div[contains(@class, "settings-section")][1]')
	await expect(section).toBeVisible()
	return section
}

async function getSystemPolicySnapshot(
	ctx: APIRequestContext,
	policyKey: string,
): Promise<SystemPolicySnapshot> {
	const response = await policyRequest(ctx, 'GET', `/apps/libresign/api/v1/policies/system/${policyKey}`)
	if (response.httpStatus === 404) {
		return {
			exists: false,
			value: null,
			allowChildOverride: true,
		}
	}

	expect(response.httpStatus, `getSystemPolicySnapshot(${policyKey}): expected 200 or 404 but got ${response.httpStatus}`).toBe(200)

	return {
		exists: true,
		value: response.data.value ?? null,
		allowChildOverride: response.data.allowChildOverride === true,
	}
}

async function restoreSystemPolicySnapshot(
	ctx: APIRequestContext,
	policyKey: string,
	snapshot: SystemPolicySnapshot,
): Promise<void> {
	if (!snapshot.exists) {
		await setSystemPolicyEntry(ctx, policyKey, null, true)
		return
	}

	const response = await policyRequest(ctx, 'POST', `/apps/libresign/api/v1/policies/system/${policyKey}`, {
		value: snapshot.value,
		allowChildOverride: snapshot.allowChildOverride,
	})
	expect(response.httpStatus, `restoreSystemPolicySnapshot(${policyKey}): expected 200 but got ${response.httpStatus}`).toBe(200)
}

async function savePreferenceAsDisabled(section: Locator): Promise<void> {
	const disabledOption = section.getByRole('radio', { name: /^(Disable metadata collection|Disabled)\b/i }).first()
	await disabledOption.click({ force: true })
}

async function clearPreference(section: Locator): Promise<void> {
	const resetButton = section.getByRole('button').filter({ hasText: 'Reset to default' }).first()
	await expect(resetButton).toBeVisible()

	await resetButton.click()
}

async function saveDocMdpPreference(section: Locator, level: 0 | 1 | 2 | 3): Promise<void> {
	const labelByLevel: Record<number, string> = {
		0: 'Disabled',
		1: 'No changes allowed',
		2: 'Form filling',
		3: 'Form filling and annotations',
	}

	const option = section.getByRole('radio', { name: new RegExp(`^${escapeRegExp(labelByLevel[level])}\\b`, 'i') }).first()
	await option.click({ force: true })
}

async function saveSignatureTextTemplatePreference(section: Locator, template: string): Promise<void> {
	const templateInput = section.getByLabel('Signature text template').first()
	await templateInput.click({ force: true })
	await templateInput.fill(template)
	await templateInput.press('Tab')
}

function escapeRegExp(value: string): string {
	return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

async function expectPolicyEffectiveValue(
	ctx: APIRequestContext,
	policyKey: string,
	expectedValue: unknown,
	expectedScope: string,
): Promise<void> {
	await expect.poll(async () => {
		const entry = await getEffectivePolicy(ctx, policyKey)
		return {
			value: entry?.effectiveValue,
			scope: entry?.sourceScope,
		}
	}, { timeout: 15000 }).toEqual({
		value: expectedValue,
		scope: expectedScope,
	})
}

async function expectDocMdpEffectiveValue(
	ctx: APIRequestContext,
	expectedValue: number,
	expectedScope: string,
): Promise<void> {
	await expect.poll(async () => {
		const entry = await getEffectivePolicy(ctx, 'docmdp')
		return {
			value: Number(entry?.effectiveValue),
			scope: entry?.sourceScope,
		}
	}, { timeout: 15000 }).toEqual({
		value: expectedValue,
		scope: expectedScope,
	})
}

async function expectSignatureTextEffectiveScope(
	ctx: APIRequestContext,
	expectedScope: string,
	expectedTemplate: string,
): Promise<void> {
	await expect.poll(async () => {
		const entry = await getEffectivePolicy(ctx, 'signature_stamp')
		if (!entry) {
			return { scope: '', template: '' }
		}
		return {
			scope: String(entry.sourceScope ?? ''),
			template: extractSignatureTextTemplate(entry.effectiveValue),
		}
	}, { timeout: 15000 }).toMatchObject({
		scope: expectedScope,
		template: expect.stringContaining(expectedTemplate),
	})
}

function extractSignatureTextTemplate(value: unknown): string {
	if (typeof value === 'string') {
		const trimmed = value.trim()
		if (trimmed.startsWith('{')) {
			try {
				const parsed = JSON.parse(trimmed) as { template?: string }
				return parsed.template ?? ''
			} catch {
				return ''
			}
		}

		return trimmed
	}

	if (value && typeof value === 'object' && 'template' in (value as Record<string, unknown>)) {
		return String((value as Record<string, unknown>).template ?? '')
	}

	return ''
}

async function setSystemNumericPolicyEntry(
	ctx: APIRequestContext,
	policyKey: string,
	value: number,
	allowChildOverride: boolean,
): Promise<void> {
	const response = await policyRequest(ctx, 'POST', `/apps/libresign/api/v1/policies/system/${policyKey}`, {
		value,
		allowChildOverride,
	})
	expect(response.httpStatus, `setSystemNumericPolicyEntry(${policyKey}): expected 200 but got ${response.httpStatus}`).toBe(200)
}

async function setGroupNumericPolicyEntry(
	ctx: APIRequestContext,
	groupId: string,
	policyKey: string,
	value: number,
	allowChildOverride: boolean,
): Promise<void> {
	const response = await policyRequest(ctx, 'PUT', `/apps/libresign/api/v1/policies/group/${groupId}/${policyKey}`, {
		value,
		allowChildOverride,
	})
	expect(response.httpStatus, `setGroupNumericPolicyEntry(${groupId}/${policyKey}): expected 200 but got ${response.httpStatus}`).toBe(200)
}
