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
		})
	})

	it('normalizes and trims canonical JSON payloads', () => {
		expect(normalizeTsaSettings('{"url":" https://tsa.example.test/tsr ","policy_oid":" 1.2.3 ","auth_type":"basic","username":" tsa-user "}')).toEqual({
			url: 'https://tsa.example.test/tsr',
			policy_oid: '1.2.3',
			auth_type: 'basic',
			username: 'tsa-user',
		})
	})

	it('drops username when authentication is not basic', () => {
		expect(normalizeTsaSettings({
			url: 'https://tsa.example.test/tsr',
			policy_oid: '1.2.3',
			auth_type: 'none',
			username: 'should-not-survive',
		})).toEqual({
			url: 'https://tsa.example.test/tsr',
			policy_oid: '1.2.3',
			auth_type: 'none',
			username: '',
		})
	})

	it('falls back to defaults for invalid payloads', () => {
		expect(normalizeTsaSettings('not-json')).toEqual(DEFAULT_TSA_SETTINGS)
		expect(normalizeTsaSettings([] as never)).toEqual(DEFAULT_TSA_SETTINGS)
		expect(normalizeTsaSettings(null as never)).toEqual(DEFAULT_TSA_SETTINGS)
	})

	it('serializes normalized TSA settings to canonical JSON', () => {
		expect(serializeTsaSettings({
			url: ' https://tsa.example.test/tsr ',
			policy_oid: ' 1.2.3 ',
			auth_type: 'basic',
			username: ' tsa-user ',
		})).toBe('{"url":"https://tsa.example.test/tsr","policy_oid":"1.2.3","auth_type":"basic","username":"tsa-user"}')
	})
})
