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

import { docMdpRealDefinition, resolveDocMdpLevel } from '../../../../../../views/Settings/PolicyWorkbench/settings/docmdp/realDefinition'

describe('docMdpRealDefinition', () => {
	it('keeps the DocMDP context and clearer post-signature description', () => {
		expect(docMdpRealDefinition.context).toBe('DocMDP')
		expect(docMdpRealDefinition.description).toBe('Control which PDF changes remain allowed after signing and help readers detect disallowed modifications.')
	})

	it('normalizes valid levels and rejects invalid ones for selection', () => {
		expect(resolveDocMdpLevel(0)).toBe(0)
		expect(resolveDocMdpLevel('3')).toBe(3)
		expect(resolveDocMdpLevel('9')).toBeNull()
		expect(docMdpRealDefinition.hasSelectableDraftValue(2)).toBe(true)
		expect(docMdpRealDefinition.hasSelectableDraftValue('nope')).toBe(false)
	})

	it('summarizes the available certification levels', () => {
		expect(docMdpRealDefinition.summarizeValue(0)).toBe('Disabled')
		expect(docMdpRealDefinition.summarizeValue(1)).toBe('No changes allowed')
		expect(docMdpRealDefinition.summarizeValue(2)).toBe('Form filling')
		expect(docMdpRealDefinition.summarizeValue(3)).toBe('Form filling and annotations')
	})
})