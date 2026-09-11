/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	normalizeSignerGeolocationValue,
	resolveSignerGeolocationMode,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/signer-geolocation/model'

describe('signer-geolocation model', () => {
	it('resolves mode from object values', () => {
		expect(resolveSignerGeolocationMode({ mode: 'disabled' })).toBe('disabled')
		expect(resolveSignerGeolocationMode({ mode: 'optional' })).toBe('optional')
		expect(resolveSignerGeolocationMode({ mode: 'required' })).toBe('required')
	})

	it('resolves mode from bare strings', () => {
		expect(resolveSignerGeolocationMode('optional')).toBe('optional')
		expect(resolveSignerGeolocationMode('required')).toBe('required')
		expect(resolveSignerGeolocationMode('disabled')).toBe('disabled')
	})

	it('rejects unsupported modes', () => {
		expect(resolveSignerGeolocationMode({ mode: 'banana' })).toBeNull()
		expect(resolveSignerGeolocationMode('banana')).toBeNull()
		expect(resolveSignerGeolocationMode(null)).toBeNull()
		expect(resolveSignerGeolocationMode(true)).toBeNull()
	})

	it('normalizes invalid values to disabled default', () => {
		expect(normalizeSignerGeolocationValue(null)).toEqual({ mode: 'disabled' })
		expect(normalizeSignerGeolocationValue({ mode: 'nope' })).toEqual({ mode: 'disabled' })
		expect(normalizeSignerGeolocationValue({ mode: 'optional' })).toEqual({ mode: 'optional' })
	})
})
