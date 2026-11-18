/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	MAX_ARCHIVE_ADDRESSES,
	normalizeSignedCopyMailPolicyConfig,
	parseArchiveAddresses,
	serializeSignedCopyMailPolicyConfig,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/signed-copy-mail/model'

describe('signed copy mail policy model', () => {
	it('treats missing and invalid values as disabled', () => {
		expect(normalizeSignedCopyMailPolicyConfig(null)).toEqual({ enabled: false, archive_addresses: [] })
		expect(normalizeSignedCopyMailPolicyConfig('{not json')).toEqual({ enabled: false, archive_addresses: [] })
	})

	it('reads the serialized backend value', () => {
		expect(normalizeSignedCopyMailPolicyConfig('{"enabled":true,"archive_addresses":["archive@example.com"]}'))
			.toEqual({ enabled: true, archive_addresses: ['archive@example.com'] })
	})

	it('serializes to the canonical payload', () => {
		expect(serializeSignedCopyMailPolicyConfig({ enabled: true, archive_addresses: ['a@example.com', 'A@example.com'] }))
			.toBe('{"enabled":true,"archive_addresses":["a@example.com"]}')
	})

	it('parses address text and drops invalid entries', () => {
		expect(parseArchiveAddresses('a@example.com; b@example.com\ninvalid')).toEqual(['a@example.com', 'b@example.com'])
		expect(parseArchiveAddresses(42)).toEqual([])
	})

	it('limits the number of archive addresses', () => {
		const addresses = Array.from({ length: 20 }, (_, i) => `archive${i}@example.com`)
		expect(parseArchiveAddresses(addresses)).toHaveLength(MAX_ARCHIVE_ADDRESSES)
	})
})
