/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	normalizeSignerIpGeolocationValue,
	resolveSignerIpGeolocationMode,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/signer-ip-geolocation/model'

describe('signer-ip-geolocation model', () => {
	it('resolves mode from object values', () => {
		expect(resolveSignerIpGeolocationMode({ mode: 'disabled' })).toBe('disabled')
		expect(resolveSignerIpGeolocationMode({ mode: 'enabled' })).toBe('enabled')
	})

	it('resolves mode from bare strings', () => {
		expect(resolveSignerIpGeolocationMode('enabled')).toBe('enabled')
		expect(resolveSignerIpGeolocationMode('disabled')).toBe('disabled')
	})

	it('rejects unsupported modes including device-only values', () => {
		expect(resolveSignerIpGeolocationMode({ mode: 'optional' })).toBeNull()
		expect(resolveSignerIpGeolocationMode({ mode: 'required' })).toBeNull()
		expect(resolveSignerIpGeolocationMode('banana')).toBeNull()
		expect(resolveSignerIpGeolocationMode(true)).toBeNull()
	})

	it('normalizes invalid values to disabled default', () => {
		expect(normalizeSignerIpGeolocationValue(null)).toEqual({ mode: 'disabled' })
		expect(normalizeSignerIpGeolocationValue({ mode: 'optional' })).toEqual({ mode: 'disabled' })
		expect(normalizeSignerIpGeolocationValue({ mode: 'enabled' })).toEqual({ mode: 'enabled' })
	})
})
