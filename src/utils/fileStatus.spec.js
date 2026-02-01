/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { FILE_STATUS } from '../constants.js'
import { buildStatusMap, getStatusLabel } from './fileStatus.js'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, text) => text,
}))

describe('fileStatus utils', () => {
	it('returns a known label for a known status', () => {
		expect(getStatusLabel(FILE_STATUS.SIGNED)).toBe('Signed')
	})

	it('returns Unknown for an unrecognized status', () => {
		expect(getStatusLabel(999)).toBe('Unknown')
	})

	it('builds status map with enum and aliases', () => {
		const map = buildStatusMap()

		expect(map[FILE_STATUS.ABLE_TO_SIGN].class).toBe('ready')
		expect(map.ABLE_TO_SIGN).toBe(map[FILE_STATUS.ABLE_TO_SIGN])
		expect(map.PENDING).toBe(map[FILE_STATUS.ABLE_TO_SIGN])
		expect(map.PARTIAL).toBe(map[FILE_STATUS.PARTIAL_SIGNED])
	})
})
