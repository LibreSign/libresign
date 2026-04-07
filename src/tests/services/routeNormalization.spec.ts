/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'

// Mock console.warn to suppress logs during tests
const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})

import { normalizeRouteRecord } from '../../services/routeNormalization.ts'

describe('normalizeRouteRecord', () => {
	beforeEach(() => {
		warnSpy.mockClear()
	})

	it('returns empty object for non-object input', () => {
		expect(normalizeRouteRecord(null, 'params')).toEqual({})
		expect(normalizeRouteRecord(undefined, 'query')).toEqual({})
		expect(normalizeRouteRecord('string', 'params')).toEqual({})
		expect(normalizeRouteRecord(123, 'query')).toEqual({})
	})

	it('rejects array input and returns empty object', () => {
		const result = normalizeRouteRecord(['value1', 'value2'], 'params')

		expect(result).toEqual({})
	})

	it('rejects array query input and returns empty object', () => {
		const result = normalizeRouteRecord(['param1', 'param2'], 'query')

		expect(result).toEqual({})
	})

	it('normalizes object with string values', () => {
		const input = {
			uuid: 'test-uuid',
			fileId: '123',
			name: 'document.pdf',
		}

		const result = normalizeRouteRecord(input, 'params')

		expect(result).toEqual(input)
	})

	it('filters non-string values', () => {
		const input = {
			uuid: 'test-uuid',
			count: 123,
			active: true,
			nullable: null,
		}

		const result = normalizeRouteRecord(input, 'query')

		expect(result).toEqual({ uuid: 'test-uuid' })
	})

	it('handles mixed string and non-string values', () => {
		const input = {
			id: 'abc123',
			status: 'pending',
			count: 0,
			flag: false,
			name: '',
		}

		const result = normalizeRouteRecord(input, 'params')

		expect(result).toEqual({
			id: 'abc123',
			status: 'pending',
			name: '',
		})
	})

	it('handles empty object', () => {
		const result = normalizeRouteRecord({}, 'query')

		expect(result).toEqual({})
	})

	it('preserves empty string values (they are valid)', () => {
		const input = {
			uuid: '',
			name: 'test',
		}

		const result = normalizeRouteRecord(input, 'params')

		expect(result).toEqual(input)
	})

	it('prevents numeric keys from array conversion by rejecting arrays', () => {
		// When Vue Router provides query: ['a', 'b', 'c'], without our protection,
		// Object.entries() would create { '0': 'a', '1': 'b', '2': 'c' }
		// Our fix rejects arrays entirely, preventing this issue
		const arrayInput: any = ['value1', 'value2', 'value3']

		const result = normalizeRouteRecord(arrayInput, 'query')

		// Should return empty object, preventing the numeric keys
		expect(result).toEqual({})
	})
})

