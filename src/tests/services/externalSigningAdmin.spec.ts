/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { describe, expect, it } from 'vitest'
import { validateExternalSigningDraft } from '../../services/externalSigningAdmin'
import type { ExternalSigningAdminAdapter, ExternalSigningDriver, ExternalSigningDraft, ExternalSigningInstance } from '../../services/externalSigningAdmin'

const driver: ExternalSigningDriver = {
	id: 'csc-v1', name: 'CSC v1', description: 'Test fixture, not live integration',
	experimental: true, available: true, unavailableReason: null,
	fields: [
		{ key: 'baseUrl', label: 'Service URL', type: 'url', required: true, secret: false, validation: { schemes: ['https'], allowUserInfo: false } },
		{ key: 'accessToken', label: 'Access token', type: 'password', required: false, secret: true, validation: {} },
	],
}

const first: ExternalSigningInstance = { id: 'one', driverId: 'csc-v1', name: 'First', enabled: false, configuration: { baseUrl: 'https://one.example.invalid' }, configuredSecretFields: ['accessToken'], revision: 1 }
const second: ExternalSigningInstance = { ...first, id: 'two', name: 'Second', configuration: { baseUrl: 'https://two.example.invalid' } }

function draft(overrides: Partial<ExternalSigningDraft> = {}): ExternalSigningDraft {
	return { driverId: 'csc-v1', name: 'First', configuration: { baseUrl: 'https://one.example.invalid' }, secretReplacements: {}, ...overrides }
}

describe('external signing administration: driver metadata and synthetic instance fixtures', () => {
	it('requires name and metadata-driven URL', () => {
		expect(validateExternalSigningDraft(driver, draft({ name: '', configuration: {} }))).toEqual({ name: 'required', baseUrl: 'required' })
	})
	it.each(['http://service.invalid', 'https://user:password@service.invalid', 'not a URL'])('rejects unsafe input %s without calling backend', value => {
			expect(validateExternalSigningDraft(driver, draft({ configuration: { baseUrl: value } }))).toEqual({ baseUrl: 'invalid' })
		})
	it.each([
		'https://localhost/api',
		'https://csc.internal/api',
		'https://127.0.0.1/api',
		'https://[::1]/api',
		'https://csc.example.org/api?token=visible',
		'https://csc.example.org/api#fragment',
		'https://csc.example.org/contains space',
		'https://csc.example.org/' + 'a'.repeat(2050),
	])('rejects endpoint values that the administrative API rejects: %s', value => {
		expect(validateExternalSigningDraft(driver, draft({ configuration: { baseUrl: value } }))).toEqual({ baseUrl: 'invalid' })
	})

	it('accepts a valid HTTPS endpoint with an ordinary path', () => {
		expect(validateExternalSigningDraft(driver, draft({ configuration: { baseUrl: 'https://csc.example.org/v1/sign' } }))).toEqual({})
	})

	it('rejects oversized secret replacement without exposing its value', () => {
		expect(validateExternalSigningDraft(driver, draft({ secretReplacements: { accessToken: 'x'.repeat(8193) } }))).toEqual({ accessToken: 'invalid' })
	})

	it('preserves configured secret on editing without reading it', () => {
		expect(validateExternalSigningDraft(driver, draft(), first)).toEqual({})
		expect(JSON.stringify(first)).not.toContain('my-secret')
	})
	it('supports two distinct instances of the same driver without coupling their state', async () => {
		const fixtureRows = [first, second]
		const mockAdapter: ExternalSigningAdminAdapter = {
			list: async () => fixtureRows.map(row => ({ ...row, configuration: { ...row.configuration } })),
			save: async next => {
				const row = fixtureRows.find(item => item.id === next.id)
				if (!row) {
					throw new Error('Unknown fixture row')
				}
				row.name = next.name
				return row
			},
			setEnabled: async (row, enabled) => ({ ...row, enabled }),
			remove: async () => {},
		}
		const rows = await mockAdapter.list()
		expect(rows.map(row => row.id)).toEqual(['one', 'two'])
		expect(rows[0].configuration.baseUrl).not.toBe(rows[1].configuration.baseUrl)
		await mockAdapter.save(draft({ id: 'two', name: 'Changed second' }))
		expect(fixtureRows[0].name).toBe('First')
		expect(fixtureRows[1].name).toBe('Changed second')
	})
})
