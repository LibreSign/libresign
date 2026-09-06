/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	DEFAULT_TSA_SETTINGS,
	normalizeTsaSettings,
	serializeTsaSettings,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/tsa/model'

describe('tsa model', () => {
	it('exposes the disabled TSA defaults', () => {
		expect(DEFAULT_TSA_SETTINGS).toEqual({
			url: '',
			policy_oid: '',
			auth_type: 'none',
			username: '',
			hash_algorithm: 'SHA256',
		})
	})

	it('normalizes and trims canonical JSON payloads', () => {
		expect(normalizeTsaSettings('{"url":" https://tsa.example.test/tsr ","policy_oid":" 1.2.3 ","auth_type":"basic","username":" tsa-user ","hash_algorithm":" sha384 "}')).toEqual({
			url: 'https://tsa.example.test/tsr',
			policy_oid: '1.2.3',
			auth_type: 'basic',
			username: 'tsa-user',
			hash_algorithm: 'SHA384',
		})
	})

	it('drops username when authentication is not basic', () => {
		expect(normalizeTsaSettings({
			url: 'https://tsa.example.test/tsr',
			policy_oid: '1.2.3',
			auth_type: 'none',
			username: 'should-not-survive',
			hash_algorithm: 'SHA512',
		})).toEqual({
			url: 'https://tsa.example.test/tsr',
			policy_oid: '1.2.3',
			auth_type: 'none',
			username: '',
			hash_algorithm: 'SHA512',
		})
	})

	it('falls back to defaults for invalid payloads', () => {
		expect(normalizeTsaSettings('not-json')).toEqual(DEFAULT_TSA_SETTINGS)
		expect(normalizeTsaSettings([] as never)).toEqual(DEFAULT_TSA_SETTINGS)
		expect(normalizeTsaSettings(null as never)).toEqual(DEFAULT_TSA_SETTINGS)
	})

	it.each([
		['SHA256', 'SHA256'],
		['SHA384', 'SHA384'],
		['SHA512', 'SHA512'],
		['sha512', 'SHA512'],
		// The authorities in #8145 reject SHA1, so it is not offered.
		['SHA1', 'SHA256'],
		['RIPEMD160', 'SHA256'],
		['whatever', 'SHA256'],
		['', 'SHA256'],
	])('normalizes the hash algorithm %s to %s', (configured, expected) => {
		expect(normalizeTsaSettings({
			url: 'https://tsa.example.test/tsr',
			hash_algorithm: configured,
		} as never).hash_algorithm).toBe(expected)
	})

	it('uses the default hash algorithm when the payload has none', () => {
		expect(normalizeTsaSettings({ url: 'https://tsa.example.test/tsr' } as never).hash_algorithm).toBe('SHA256')
	})

	it('serializes normalized TSA settings to canonical JSON', () => {
		expect(serializeTsaSettings({
			url: ' https://tsa.example.test/tsr ',
			policy_oid: ' 1.2.3 ',
			auth_type: 'basic',
			username: ' tsa-user ',
			hash_algorithm: 'SHA512',
		})).toBe('{"url":"https://tsa.example.test/tsr","policy_oid":"1.2.3","auth_type":"basic","username":"tsa-user","hash_algorithm":"SHA512"}')
	})
})
