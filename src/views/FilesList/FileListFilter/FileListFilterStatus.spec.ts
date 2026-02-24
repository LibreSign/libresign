/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, it, expect } from 'vitest'

describe('FileListFilterStatus - SVG fix', () => {
	it('chips created by setPreset must not have icon property', () => {
		const fileStatus = [
			{ id: 1, label: 'Draft' },
			{ id: 2, label: 'Ready to sign' },
		]

		const chips = []
		for (const id of [1, 2]) {
			const status = fileStatus.find(item => item.id === id)
			if (!status) continue

			chips.push({
				id: status.id,
				text: status.label,
				onclick: () => {},
			})
		}

		chips.forEach((chip) => {
			expect(chip).not.toHaveProperty('icon')
			expect(chip.text).toBeTruthy()
		})
	})

	it('chips created by setMarkedFilter must not have icon property', () => {
		const fileStatus = [
			{ id: 1, label: 'Draft' },
			{ id: 3, label: 'Partially signed' },
		]

		const chips = []
		for (const id of [1, 3]) {
			const status = fileStatus.find(item => item.id === id)
			if (!status) continue

			chips.push({
				id: status.id,
				text: status.label,
				onclick: () => {},
			})
		}

		expect(chips.length).toBeGreaterThan(0)
		chips.forEach((chip) => {
			expect(chip).not.toHaveProperty('icon')
		})
	})

	it('prevents SVG warning by not including icon in chip object', () => {
		const chip = {
			id: 1,
			text: 'Draft',
			onclick: () => {},
		}

		expect(chip).not.toHaveProperty('icon')

		const BAD_CHIP = {
			id: 1,
			text: 'Draft',
			icon: 'M12 2C6.48...',
			onclick: () => {},
		}

		expect(BAD_CHIP).toHaveProperty('icon')
		expect(chip).not.toHaveProperty('icon')
	})
})

