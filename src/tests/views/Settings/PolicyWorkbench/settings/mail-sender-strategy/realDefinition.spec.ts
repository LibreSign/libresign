/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

import { mailSenderStrategyRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/mail-sender-strategy/realDefinition'

describe('mailSenderStrategyRealDefinition', () => {
	it('registers the policy key and copy', () => {
		expect(mailSenderStrategyRealDefinition.key).toBe('mail_sender_strategy')
		expect(mailSenderStrategyRealDefinition.title).toBe('Notification email sender')
		expect(mailSenderStrategyRealDefinition.description).toContain('system mailer')
	})

	it('supports only the system scope and hides the workbench card for group admins', () => {
		expect(mailSenderStrategyRealDefinition.supportedScopes).toEqual(['system'])
		expect(mailSenderStrategyRealDefinition.groupAdminBehavior?.canRenderPolicy?.({
			editableByCurrentActor: false,
			canSaveAsUserDefault: false,
			meta: { canCreateDescendantRules: false },
		} as never)).toBe(false)
	})

	it('starts from the system mailer and always offers a selectable draft value', () => {
		expect(mailSenderStrategyRealDefinition.createEmptyValue()).toBe('system')
		expect(mailSenderStrategyRealDefinition.hasSelectableDraftValue('system')).toBe(true)
		expect(mailSenderStrategyRealDefinition.hasSelectableDraftValue('requester')).toBe(true)
	})

	it('normalizes draft values to a known strategy', () => {
		expect(mailSenderStrategyRealDefinition.normalizeDraftValue('requester')).toBe('requester')
		expect(mailSenderStrategyRealDefinition.normalizeDraftValue(' System ')).toBe('system')
		expect(mailSenderStrategyRealDefinition.normalizeDraftValue('unknown')).toBe('system')
		expect(mailSenderStrategyRealDefinition.normalizeDraftValue(null)).toBe('system')
	})

	it('disables child-override toggles for every scope', () => {
		expect(mailSenderStrategyRealDefinition.normalizeAllowChildOverride('system', true)).toBe(false)
		expect(mailSenderStrategyRealDefinition.normalizeAllowChildOverride('system', false)).toBe(false)
		expect(mailSenderStrategyRealDefinition.normalizeAllowChildOverride('group', true)).toBe(false)
	})

	it('falls back to the persisted system value only when it comes from the system scope', () => {
		expect(mailSenderStrategyRealDefinition.getFallbackSystemDefault('requester', 'system')).toBe('requester')
		expect(mailSenderStrategyRealDefinition.getFallbackSystemDefault('REQUESTER', 'system')).toBe('requester')
		expect(mailSenderStrategyRealDefinition.getFallbackSystemDefault('requester', 'group')).toBe('system')
		expect(mailSenderStrategyRealDefinition.getFallbackSystemDefault(null, 'system')).toBe('system')
		expect(mailSenderStrategyRealDefinition.getFallbackSystemDefault(undefined, null)).toBe('system')
	})

	it('summarizes each strategy with a human readable label', () => {
		expect(mailSenderStrategyRealDefinition.summarizeValue('system')).toBe('System mailer')
		expect(mailSenderStrategyRealDefinition.summarizeValue('requester')).toBe('Requester mail account')
		expect(mailSenderStrategyRealDefinition.summarizeValue('unknown')).toBe('System mailer')
	})

	it('explains that lower scopes cannot customize the setting', () => {
		expect(mailSenderStrategyRealDefinition.formatAllowOverride(true)).toBe('Lower-level customization is disabled for this setting')
		expect(mailSenderStrategyRealDefinition.formatAllowOverride(false)).toBe('Lower-level customization is disabled for this setting')
	})

	it('passes the mail provider availability from the policy meta to the editor', () => {
		const resolve = mailSenderStrategyRealDefinition.resolveEditorProps!
		expect(resolve({ meta: { mailProviderAvailable: false } } as never, { foo: 'bar' })).toEqual({ foo: 'bar', mailProviderAvailable: false })
		expect(resolve({ meta: { mailProviderAvailable: true } } as never, {})).toEqual({ mailProviderAvailable: true })
		expect(resolve({ meta: {} } as never, {})).toEqual({ mailProviderAvailable: true })
		expect(resolve(null, {})).toEqual({ mailProviderAvailable: true })
	})
})
