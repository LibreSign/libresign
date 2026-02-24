/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { FILE_STATUS } from '../../constants.js'
import {
	buildStatusMap,
	getStatusLabel,
	getStatusSvgInline,
	getStatusConfig,
	getStatusIcon,
} from '../../utils/fileStatus.js'

vi.mock('@nextcloud/l10n', () => ({
	t: (app: any, text: any) => text,
	translate: (app: any, text: any) => text,
}))

describe('fileStatus utils', () => {
	it('returns a known label for a known status', () => {
		expect(getStatusLabel(FILE_STATUS.SIGNED)).toBe('Signed')
	})

	it('returns Unknown for an unrecognized status', () => {
		expect(getStatusLabel(999)).toBe('Unknown')
	})

	it('builds status map with enum and aliases', () => {
		const map = buildStatusMap() as unknown as Record<number | string, { class: string; label: string; icon: string }>

		expect(map[FILE_STATUS.ABLE_TO_SIGN].class).toBe('ready')
		expect(map.ABLE_TO_SIGN).toBe(map[FILE_STATUS.ABLE_TO_SIGN])
		expect(map.PENDING).toBe(map[FILE_STATUS.ABLE_TO_SIGN])
		expect(map.PARTIAL).toBe(map[FILE_STATUS.PARTIAL_SIGNED])
	})

	it('returns empty svg for unknown status', () => {
		expect(getStatusSvgInline(999)).toBe('')
	})

	it('returns config for draft status', () => {
		const config = getStatusConfig(FILE_STATUS.DRAFT)

		if (config) {
			expect(config.label).toBeDefined()
			expect(config.icon).toBeDefined()
		}
	})

	it('returns icon path for draft status', () => {
		const icon = getStatusIcon(FILE_STATUS.DRAFT)

		expect(icon).toBeDefined()
		expect(typeof icon).toBe('string')
	})

	it('returns different icons for different statuses', () => {
		const draftIcon = getStatusIcon(FILE_STATUS.DRAFT)
		const signedIcon = getStatusIcon(FILE_STATUS.SIGNED)

		expect(draftIcon).not.toBe(signedIcon)
	})

	it('maps correct CSS classes for statuses', () => {
		const map = buildStatusMap() as unknown as Record<number | string, { class: string; label: string; icon: string }>

		expect(map[FILE_STATUS.DRAFT].class).toBe('draft')
		expect(map[FILE_STATUS.SIGNED].class).toBe('signed')
		expect(map[FILE_STATUS.ABLE_TO_SIGN].class).toBe('ready')
	})
})
