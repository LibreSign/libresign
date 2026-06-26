/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

import { collectMetadataRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/collect-metadata/realDefinition'

describe('collectMetadataRealDefinition', () => {
	it('normalizes booleans and legacy boolean-like values to canonical draft booleans', () => {
		expect(collectMetadataRealDefinition.createEmptyValue?.()).toBe(false)
		expect(collectMetadataRealDefinition.normalizeDraftValue?.(true)).toBe(true)
		expect(collectMetadataRealDefinition.normalizeDraftValue?.('1')).toBe(true)
		expect(collectMetadataRealDefinition.normalizeDraftValue?.('true')).toBe(true)
		expect(collectMetadataRealDefinition.normalizeDraftValue?.(false)).toBe(false)
		expect(collectMetadataRealDefinition.normalizeDraftValue?.('0')).toBe(false)
		expect(collectMetadataRealDefinition.normalizeDraftValue?.('invalid')).toBe(false)
	})

	it('reports whether the current value is selectable and summarizes configured states', () => {
		expect(collectMetadataRealDefinition.hasSelectableDraftValue?.(true)).toBe(true)
		expect(collectMetadataRealDefinition.hasSelectableDraftValue?.('false')).toBe(true)
		expect(collectMetadataRealDefinition.hasSelectableDraftValue?.('invalid')).toBe(false)

		expect(collectMetadataRealDefinition.summarizeValue?.(true)).toBe('Enabled')
		expect(collectMetadataRealDefinition.summarizeValue?.('0')).toBe('Disabled')
		expect(collectMetadataRealDefinition.summarizeValue?.('invalid')).toBe('Not configured')
	})

	it('falls back to system default false unless an explicit system value is present', () => {
		expect(collectMetadataRealDefinition.getFallbackSystemDefault?.(true, 'system')).toBe(true)
		expect(collectMetadataRealDefinition.getFallbackSystemDefault?.(true, 'global')).toBe(false)
		expect(collectMetadataRealDefinition.getFallbackSystemDefault?.(null, null)).toBe(false)
	})
})
