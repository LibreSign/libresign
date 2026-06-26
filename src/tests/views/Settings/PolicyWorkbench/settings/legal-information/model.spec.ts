/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import { normalizeLegalInformation } from '../../../../../../views/Settings/PolicyWorkbench/settings/legal-information/model'

describe('legal-information model', () => {
	it('keeps string values untouched', () => {
		expect(normalizeLegalInformation('Custom legal information')).toBe('Custom legal information')
	})

	it('stringifies numeric and boolean values for legacy compatibility in the editor model', () => {
		expect(normalizeLegalInformation(123 as never)).toBe('123')
		expect(normalizeLegalInformation(true as never)).toBe('true')
		expect(normalizeLegalInformation(false as never)).toBe('false')
	})

	it('falls back to the empty string for nullish and unsupported values', () => {
		expect(normalizeLegalInformation(null as never)).toBe('')
		expect(normalizeLegalInformation({ text: 'nope' } as never)).toBe('')
	})
})
