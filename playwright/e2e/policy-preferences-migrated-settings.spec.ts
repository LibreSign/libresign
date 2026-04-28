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
	setAppConfig,
} from '../support/nc-provisioning'
import {
	clearUserPolicyPreference,
	createAuthenticatedRequestContext,
	getEffectivePolicy,
	policyRequest,
	setGroupPolicyEntry,
	setSystemPolicyEntry,
} from '../support/policy-api'

const adminUser = 'admin'
const adminPass = process.env.ADMIN_PASSWORD || 'admin'

test.describe('Policy preferences: migrated settings', () => {
	test('user can save and clear collect_metadata, identification_documents, docmdp and signature_text preferences', async ({ page }) => {
		const groupId = `pref-migrated-${Date.now()}`
		const endUser = `prefmigrated_${Date.now()}`
		const endPass = 'user1234'

		const adminCtx = await createAuthenticatedRequestContext(adminUser, adminPass)
		let endUserCtx: APIRequestContext | null = null
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
			await setAppConfig(adminCtx, 'libresign', 'certificate_engine', 'openssl')
			await configureOpenSsl(adminCtx, 'LibreSign Test', {
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
			await setSystemPolicyEntry(adminCtx, 'identification_documents', JSON.stringify(false), true)
			await setGroupPolicyEntry(adminCtx, groupId, 'identification_documents', JSON.stringify(true), true)
			await setSystemNumericPolicyEntry(adminCtx, 'docmdp', 0, true)
			await setGroupNumericPolicyEntry(adminCtx, groupId, 'docmdp', 2, true)
			await setSystemPolicyEntry(adminCtx, 'signature_text', signatureTextSystemValue, true)
			await setGroupPolicyEntry(adminCtx, groupId, 'signature_text', signatureTextGroupValue, true)

			endUserCtx = await createAuthenticatedRequestContext(endUser, endPass)

			await login(page.request, endUser, endPass)
			await page.goto('/index.php/apps/libresign/f/preferences')
			await page.waitForLoadState('networkidle')
			await expandSettingsMenu(page)

			const collectMetadataSection = await sectionByTitle(page, 'Collect signer metadata')
			const idDocsSection = await sectionByTitle(page, 'Identification documents flow')
			const docMdpSection = await sectionByTitle(page, 'PDF certification')
			const signatureTextSection = await sectionByTitle(page, 'Signature text')

			expect(await collectMetadataSection.isVisible()).toBe(true)
			expect(await idDocsSection.isVisible()).toBe(true)
			expect(await docMdpSection.isVisible()).toBe(true)
			expect(await signatureTextSection.isVisible()).toBe(true)

			await savePreferenceAsDisabled(page, collectMetadataSection, 'collect_metadata')
			await savePreferenceAsDisabled(page, idDocsSection, 'identification_documents')
			await saveDocMdpPreference(page, docMdpSection, 3)
			await saveSignatureTextTemplatePreference(page, signatureTextSection, 'User custom template')

			await expectPolicyEffectiveValue(endUserCtx, 'collect_metadata', false, 'user')
			await expectPolicyEffectiveValue(endUserCtx, 'identification_documents', false, 'user')
			await expectDocMdpEffectiveValue(endUserCtx, 3, 'user')
			await expectSignatureTextEffectiveScope(endUserCtx, 'user', 'User custom template')

			await clearPreference(page, collectMetadataSection, 'collect_metadata')
			await clearPreference(page, idDocsSection, 'identification_documents')
			await clearPreference(page, docMdpSection, 'docmdp')
			await clearPreference(page, signatureTextSection, 'signature_text')

			await expectPolicyEffectiveValue(endUserCtx, 'collect_metadata', true, 'group')
			await expectPolicyEffectiveValue(endUserCtx, 'identification_documents', true, 'group')
			await expectDocMdpEffectiveValue(endUserCtx, 2, 'group')
			await expectSignatureTextEffectiveScope(endUserCtx, 'group', 'Group template')
		} finally {
			if (endUserCtx) {
				await clearUserPolicyPreference(endUserCtx, 'collect_metadata', [200, 401, 500])
				await clearUserPolicyPreference(endUserCtx, 'identification_documents', [200, 401, 500])
				await clearUserPolicyPreference(endUserCtx, 'docmdp', [200, 401, 500])
				await clearUserPolicyPreference(endUserCtx, 'signature_text', [200, 401, 500])
				await endUserCtx.dispose()
			}

			await policyRequest(adminCtx, 'DELETE', `/cloud/users/${endUser}`)
			await policyRequest(adminCtx, 'DELETE', `/cloud/groups/${groupId}`)
			await adminCtx.dispose()
		}
	})
})

async function sectionByTitle(page: Page, title: string): Promise<Locator> {
	const heading = page.getByRole('heading', { name: title }).first()
	await expect(heading).toBeVisible()
	const section = heading.locator('xpath=ancestor::div[contains(@class, "settings-section")][1]')
	await expect(section).toBeVisible()
	return section
}

async function savePreferenceAsDisabled(page: Page, section: Locator, policyKey: string): Promise<void> {
	const disabledOption = section.getByText('Disabled', { exact: true }).first()

	await Promise.all([
		page.waitForRequest((req) => req.method() === 'PUT'
			&& req.url().includes(`/ocs/v2.php/apps/libresign/api/v1/policies/user/${policyKey}`)),
		disabledOption.click(),
	])

	await expect(section.getByText('Preference saved')).toBeVisible()
}

async function clearPreference(page: Page, section: Locator, policyKey: string): Promise<void> {
	const resetButton = section.getByRole('button', { name: 'Reset to default' })
	await expect(resetButton).toBeVisible()

	await Promise.all([
		page.waitForRequest((req) => req.method() === 'DELETE'
			&& req.url().includes(`/ocs/v2.php/apps/libresign/api/v1/policies/user/${policyKey}`)),
		resetButton.click(),
	])
}

async function saveDocMdpPreference(page: Page, section: Locator, level: 0 | 1 | 2 | 3): Promise<void> {
	const labelByLevel: Record<number, string> = {
		0: 'Disabled',
		1: 'No changes allowed',
		2: 'Form filling',
		3: 'Form filling and annotations',
	}

	const option = section.getByText(labelByLevel[level], { exact: true }).first()

	await Promise.all([
		page.waitForRequest((req) => req.method() === 'PUT'
			&& req.url().includes('/ocs/v2.php/apps/libresign/api/v1/policies/user/docmdp')),
		option.click(),
	])

	await expect(section.getByText('Preference saved')).toBeVisible()
}

async function saveSignatureTextTemplatePreference(page: Page, section: Locator, template: string): Promise<void> {
	const templateInput = section.getByLabel('Signature text template').first()

	await Promise.all([
		page.waitForRequest((req) => req.method() === 'PUT'
			&& req.url().includes('/ocs/v2.php/apps/libresign/api/v1/policies/user/signature_text')),
		templateInput.fill(template),
	])

	await expect(section.getByText('Preference saved')).toBeVisible()
}

async function expectPolicyEffectiveValue(
	ctx: APIRequestContext,
	policyKey: string,
	expectedValue: boolean,
	expectedScope: string,
): Promise<void> {
	const entry = await getEffectivePolicy(ctx, policyKey)
	expect(entry).not.toBeNull()
	expect(entry?.effectiveValue).toBe(expectedValue)
	expect(entry?.sourceScope).toBe(expectedScope)
}

async function expectDocMdpEffectiveValue(
	ctx: APIRequestContext,
	expectedValue: number,
	expectedScope: string,
): Promise<void> {
	const entry = await getEffectivePolicy(ctx, 'docmdp')
	expect(entry).not.toBeNull()
	expect(Number(entry?.effectiveValue)).toBe(expectedValue)
	expect(entry?.sourceScope).toBe(expectedScope)
}

async function expectSignatureTextEffectiveScope(
	ctx: APIRequestContext,
	expectedScope: string,
	expectedTemplate: string,
): Promise<void> {
	const entry = await getEffectivePolicy(ctx, 'signature_text')
	expect(entry).not.toBeNull()
	expect(entry?.sourceScope).toBe(expectedScope)
	const template = extractSignatureTextTemplate(entry?.effectiveValue)
	expect(template).toContain(expectedTemplate)
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
