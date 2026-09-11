/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import { observerProfileRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/observer-profile/realDefinition'

describe('observerProfileRealDefinition', () => {
	it('defaults observer profile policy to disabled', () => {
		expect(observerProfileRealDefinition.createEmptyValue()).toBe(false)
		expect(observerProfileRealDefinition.normalizeDraftValue(null)).toBe(false)
	})

	it('summarizes enabled and disabled values', () => {
		expect(observerProfileRealDefinition.summarizeValue(true)).toBe('Enabled')
		expect(observerProfileRealDefinition.summarizeValue(false)).toBe('Disabled')
	})
})
