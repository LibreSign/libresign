/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	DEFAULT_HASH_ALGORITHM,
	HASH_ALGORITHMS,
	normalizeHashAlgorithm,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-hash-algorithm/model'

describe('signature-hash-algorithm model', () => {
	it('keeps SHA256 as default while exposing the legacy-compatible algorithm list', () => {
		expect(DEFAULT_HASH_ALGORITHM).toBe('SHA256')
		expect(HASH_ALGORITHMS).toEqual(['SHA1', 'SHA256', 'SHA384', 'SHA512', 'RIPEMD160'])
	})

	it('normalizes legacy and valid values while keeping the secure default fallback', () => {
		expect(normalizeHashAlgorithm('SHA512')).toBe('SHA512')
		expect(normalizeHashAlgorithm('sha384')).toBe('SHA384')
		expect(normalizeHashAlgorithm('SHA1')).toBe('SHA1')
		expect(normalizeHashAlgorithm('invalid')).toBe('SHA256')
	})
})