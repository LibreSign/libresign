/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string, vars?: Record<string, string>) => text.replace(/\{(\w+)\}/g, (_match, key) => vars?.[key] ?? `{${key}}`),
	getLanguage: () => 'en',
	isRTL: () => false,
}))

import { signedCopyMailRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/signed-copy-mail/realDefinition'

describe('signedCopyMailRealDefinition', () => {
	it('is disabled by default', () => {
		expect(signedCopyMailRealDefinition.createEmptyValue?.()).toBe('{"enabled":false,"archive_addresses":[]}')
		expect(signedCopyMailRealDefinition.getFallbackSystemDefault?.(null, null)).toBe('{"enabled":false,"archive_addresses":[]}')
	})

	it('supports instance, group, and account rule scopes', () => {
		expect(signedCopyMailRealDefinition.supportedScopes).toEqual(['system', 'group', 'user'])
	})

	it('summarizes the value', () => {
		expect(signedCopyMailRealDefinition.summarizeValue?.('{"enabled":false,"archive_addresses":[]}')).toBe('Disabled')
		expect(signedCopyMailRealDefinition.summarizeValue?.('{"enabled":true,"archive_addresses":[]}')).toBe('Enabled')
		expect(signedCopyMailRealDefinition.summarizeValue?.('{"enabled":true,"archive_addresses":["a@example.com","b@example.com"]}'))
			.toBe('Enabled • archive: a@example.com, b@example.com')
	})
})
