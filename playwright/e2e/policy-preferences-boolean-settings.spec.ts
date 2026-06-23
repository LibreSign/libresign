/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect, type APIRequestContext, type Locator, type Page } from '@playwright/test'

import { login } from '../support/nc-login'
import { expandSettingsMenu } from '../support/nc-navigation'
import {
	configureOpenSsl,
	deleteGroup,
	deleteUser,
	ensureGroupExists,
	ensureUserExists,
	ensureUserInGroup,
} from '../support/nc-provisioning'
import {
	clearUserPolicyPreference,
	createAuthenticatedRequestContext,
	getEffectivePolicy,
	getSystemPolicySnapshot,
	policyRequest,
	restoreSystemPolicySnapshot,
	setGroupPolicyEntry,
	setSystemPolicyEntry,
	type SystemPolicySnapshot,
} from '../support/policy-api'

const adminUser = 'admin'
const adminPass = process.env.ADMIN_PASSWORD || 'admin'
const TEST_GROUP_ID = 'pw-pref-boolean-group'
const TEST_END_USER = 'pw_prefboolean_user'

let activeAdminCtx: APIRequestContext | null = null
let endUserCtx: APIRequestContext | null = null
let originalGroupsRequestSign: SystemPolicySnapshot | null = null
let originalCollectMetadata: SystemPolicySnapshot | null = null
let originalDocmdp: SystemPolicySnapshot | null = null
let originalSignatureText: SystemPolicySnapshot | null = null

test.describe('Policy preferences: boolean settings', () => {
	test.afterEach(async () => {
		if (endUserCtx) {
			await clearUserPolicyPreference(endUserCtx, 'collect_metadata', [200, 401, 500])
			await clearUserPolicyPreference(endUserCtx, 'docmdp', [200, 401, 500])
			await clearUserPolicyPreference(endUserCtx, 'signature_stamp', [200, 401, 405, 500])
			await endUserCtx.dispose()
			endUserCtx = null
		}

		const adminCtx = activeAdminCtx ?? await createAuthenticatedRequestContext(adminUser, adminPass)
		if (originalGroupsRequestSign) {
			await restoreSystemPolicySnapshot(adminCtx, 'groups_request_sign', originalGroupsRequestSign)
		}
		if (originalCollectMetadata) {
			await restoreSystemPolicySnapshot(adminCtx, 'collect_metadata', originalCollectMetadata)
		}
		if (originalDocmdp) {
			await restoreSystemPolicySnapshot(adminCtx, 'docmdp', originalDocmdp)
		}
		if (originalSignatureText) {
			await restoreSystemPolicySnapshot(adminCtx, 'signature_stamp', originalSignatureText)
		}

		await deleteUser(adminCtx, TEST_END_USER, adminUser, adminPass)
		await deleteGroup(adminCtx, TEST_GROUP_ID, adminUser, adminPass).catch(() => {})
		await adminCtx.dispose()

		activeAdminCtx = null
		originalGroupsRequestSign = null
		originalCollectMetadata = null
		originalDocmdp = null
		originalSignatureText = null
	})

	test('user can save and clear collect_metadata/docmdp while signature_text follows group policy', async ({ page }) => {
		test.setTimeout(180000)
		const groupId = TEST_GROUP_ID
		const endUser = TEST_END_USER
		const endPass = 'user1234'

		activeAdminCtx = await createAuthenticatedRequestContext(adminUser, adminPass)
		const adminCtx = activeAdminCtx
		originalGroupsRequestSign = await getSystemPolicySnapshot(adminCtx, 'groups_request_sign')
		originalCollectMetadata = await getSystemPolicySnapshot(adminCtx, 'collect_metadata')
		originalDocmdp = await getSystemPolicySnapshot(adminCtx, 'docmdp')
		originalSignatureText = await getSystemPolicySnapshot(adminCtx, 'signature_stamp')
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

		const docMdpSection = await sectionByTitle(page, 'PDF certification')
		const signatureTextSection = await sectionByTitle(page, /Signature stamp text|Signature text|Signature stamp/i)

		await expect(page.getByRole('heading', { name: 'Collect signer metadata' })).toHaveCount(0)
		expect(await docMdpSection.isVisible()).toBe(true)
		expect(await signatureTextSection.isVisible()).toBe(true)

		await saveDocMdpPreference(docMdpSection, 3)
		await saveSignatureTextCollectMetadataPreference(signatureTextSection, false)

		await expectPolicyEffectiveValue(endUserCtx, 'collect_metadata', false, 'user')
		await expectDocMdpEffectiveValue(endUserCtx, 3, 'user')
		await expectSignatureTextEffectiveState(endUserCtx, 'group', {
			templateContains: 'Group template',
			renderMode: 'text',
		})

		await clearPreference(signatureTextSection)
		await clearPreference(docMdpSection)

		await expectPolicyEffectiveValue(endUserCtx, 'collect_metadata', true, 'group')
		await expectDocMdpEffectiveValue(endUserCtx, 2, 'group')
		await expectSignatureTextEffectiveState(endUserCtx, 'group', {
			templateContains: 'Group template',
			renderMode: 'text',
		})
	})
})

/**
 * Resolves the settings section container from a visible section heading.
 *
 * @param page Browser page containing the preferences screen.
 * @param title Visible heading text used to locate the section.
 */
async function sectionByTitle(page: Page, title: string | RegExp): Promise<Locator> {
	const heading = page.getByRole('heading', { name: title }).first()
	await expect(heading).toBeVisible()
	const section = heading.locator('xpath=ancestor::div[contains(@class, "settings-section")][1]')
	await expect(section).toBeVisible()
	return section
}

/**
 * Clears a user preference so the effective value falls back to the inherited layer.
 *
 * @param section Preference section locator.
 */
async function clearPreference(section: Locator): Promise<void> {
	const resetButton = section.getByRole('button').filter({ hasText: 'Reset to default' }).first()
	await expect(resetButton).toBeVisible()

	await resetButton.click()
}

/**
 * Selects a specific PDF certification level in the DocMDP preference section.
 *
 * @param section Preference section locator.
 * @param level Desired DocMDP level.
 */
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

/**
 * Escapes user-facing labels before embedding them in a dynamic regular expression.
 *
 * @param value Raw label text.
 */
function escapeRegExp(value: string): string {
	return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/**
 * Toggles the signature-stamp metadata checkbox to the requested value.
 *
 * @param section Preference section locator.
 * @param enabled Whether metadata collection should remain enabled.
 */
async function saveSignatureTextCollectMetadataPreference(section: Locator, enabled: boolean): Promise<void> {
	const toggle = section.getByRole('checkbox', { name: /Collect signer metadata/i }).first()
	await expect(toggle).toBeVisible()
	const checked = await toggle.isChecked()
	if (checked !== enabled) {
		await toggle.click({ force: true })
	}
}

/**
 * Polls until the effective value and source scope for a policy match the expectation.
 *
 * @param ctx Authenticated request context for the end user.
 * @param policyKey Policy key to query.
 * @param expectedValue Expected effective value.
 * @param expectedScope Expected source scope.
 */
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

/**
 * Polls until the effective DocMDP value matches the expected inherited scope.
 *
 * @param ctx Authenticated request context for the end user.
 * @param expectedValue Expected numeric DocMDP value.
 * @param expectedScope Expected source scope.
 */
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

/**
 * Polls until the effective signature-stamp state matches the expected scope and content.
 *
 * @param ctx Authenticated request context for the end user.
 * @param expectedScope Expected source scope.
 * @param expected Expected content matchers for the signature-stamp value.
 * @param expected.templateContains Expected template substring when provided.
 * @param expected.renderMode Expected render mode when provided.
 */
async function expectSignatureTextEffectiveState(
	ctx: APIRequestContext,
	expectedScope: string,
	expected: {
		templateContains?: string
		renderMode?: string
	},
): Promise<void> {
	const expectedMatch: Record<string, unknown> = {
		scope: expectedScope,
	}
	if (expected.templateContains) {
		expectedMatch.template = expect.stringContaining(expected.templateContains)
	}
	if (expected.renderMode) {
		expectedMatch.renderMode = expected.renderMode
	}

	await expect.poll(async () => {
		const entry = await getEffectivePolicy(ctx, 'signature_stamp')
		if (!entry) {
			return { scope: '', template: '', renderMode: '' }
		}
		const parsed = parseSignatureTextValue(entry.effectiveValue)
		return {
			scope: String(entry.sourceScope ?? ''),
			template: parsed.template,
			renderMode: parsed.renderMode,
		}
	}, { timeout: 15000 }).toMatchObject(expectedMatch)
}

/**
 * Normalizes a signature-stamp value returned as a stringified JSON blob or object.
 *
 * @param value Raw signature-stamp policy value.
 */
function parseSignatureTextValue(value: unknown): { template: string; renderMode: string } {
	if (typeof value === 'string') {
		const trimmed = value.trim()
		if (!trimmed.startsWith('{')) {
			return { template: trimmed, renderMode: '' }
		}

		try {
			const parsed = JSON.parse(trimmed) as { template?: string; render_mode?: string }
			return {
				template: String(parsed.template ?? ''),
				renderMode: String(parsed.render_mode ?? ''),
			}
		} catch {
			return { template: '', renderMode: '' }
		}
	}

	if (value && typeof value === 'object') {
		const raw = value as Record<string, unknown>
		return {
			template: String(raw.template ?? ''),
			renderMode: String(raw.render_mode ?? ''),
		}
	}

	return { template: '', renderMode: '' }
}

/**
 * Writes a numeric system policy entry using the generic OCS helper.
 *
 * @param ctx Authenticated admin request context.
 * @param policyKey Policy key to write.
 * @param value Numeric value to store.
 * @param allowChildOverride Whether lower layers may override the stored value.
 */
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

/**
 * Writes a numeric group policy entry using the generic OCS helper.
 *
 * @param ctx Authenticated admin request context.
 * @param groupId Group identifier receiving the rule.
 * @param policyKey Policy key to write.
 * @param value Numeric value to store.
 * @param allowChildOverride Whether lower layers may override the stored value.
 */
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
