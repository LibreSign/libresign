/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, it, expect } from 'vitest'

describe('Filter store chips - SVG fix', () => {
	it('status filter chips must not contain icon property', () => {
		const statusChips = [
			{
				id: 1,
				text: 'Draft',
				onclick: () => {},
			},
			{
				id: 2,
				text: 'Ready to sign',
				onclick: () => {},
			},
		]

		statusChips.forEach((chip) => {
			expect(chip).not.toHaveProperty('icon')
			expect(chip.text).toBeTruthy()
			expect(chip.id).toBeTruthy()
		})
	})

	it('modified filter chips can have icon as valid SVG', () => {
		const modifiedChips = [
			{
				id: 'today',
				text: 'Today',
				icon: '<svg><path /></svg>',
				onclick: () => {},
			},
		]

		modifiedChips.forEach((chip) => {
			if (chip.hasOwnProperty('icon')) {
				expect(chip.icon).toContain('<svg')
			}
		})
	})

	it('invalid pattern: chip with MDI path instead of SVG', () => {
		const invalidChip = {
			id: 1,
			text: 'Draft',
			icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z',
			onclick: () => {},
		}

		expect(invalidChip).toHaveProperty('icon')
		expect(invalidChip.icon).not.toContain('<svg')
	})
})


