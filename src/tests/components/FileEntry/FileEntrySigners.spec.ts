/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

let FileEntrySigners: any

vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((app: any, text: any) => text),
}))

beforeAll(async () => {
	;({ default: FileEntrySigners } = await import('../../../views/FilesList/FileEntry/FileEntrySigners.vue'))
})

describe('FileEntrySigners', () => {
	const createWrapper = (props = {}) => {
		return mount(FileEntrySigners, {
			propsData: {
				signersCount: 0,
				signers: [],
				...props,
			},
		})
	}

	describe('DISPLAY: Shows signer count accurately', () => {
		it('does not render component when signersCount is 0', () => {
			const wrapper = createWrapper({
				signersCount: 0,
				signers: [],
			})

			expect(wrapper.find('.signers-count').exists()).toBe(false)
		})

		it('displays "1 signer" with singular form', () => {
			const wrapper = createWrapper({
				signersCount: 1,
				signers: [{ displayName: 'John Doe' }],
			})

			expect(wrapper.find('.signers-count').text()).toContain('1')
		})

		it('displays "3 signers" with plural form', () => {
			const wrapper = createWrapper({
				signersCount: 3,
				signers: [
					{ displayName: 'John Doe' },
					{ displayName: 'Jane Smith' },
					{ displayName: 'Bob Wilson' },
				],
			})

			expect(wrapper.find('.signers-count').text()).toContain('3')
		})

		it('displays count even when signers array is empty but count is provided', () => {
			const wrapper = createWrapper({
				signersCount: 5,
				signers: [],
			})

			expect(wrapper.find('.signers-count').text()).toContain('5')
		})
	})

	describe('BEHAVIOR: Tooltip shows signer details on hover', () => {
		it('shows tooltip with signer names when hovering', async () => {
			const wrapper = createWrapper({
				signersCount: 2,
				signers: [
					{ displayName: 'John Doe', email: 'john@example.com' },
					{ displayName: 'Jane Smith', email: 'jane@example.com' },
				],
			})

			const tooltip = wrapper.vm.tooltipContent
			expect(tooltip.content).toBeTruthy()
			expect(tooltip.content).toContain('John Doe')
			expect(tooltip.content).toContain('Jane Smith')
			expect(tooltip.html).toBe(true)
		})

		it('tooltip contains all signer display names', () => {
			const wrapper = createWrapper({
				signersCount: 3,
				signers: [
					{ displayName: 'Alice' },
					{ displayName: 'Bob' },
					{ displayName: 'Charlie' },
				],
			})

			const tooltip = wrapper.vm.tooltipContent
			expect(tooltip.content).toContain('Alice')
			expect(tooltip.content).toContain('Bob')
			expect(tooltip.content).toContain('Charlie')
		})

		it('does not render element when no signers', () => {
			const wrapper = createWrapper({
				signersCount: 0,
				signers: [],
			})

			expect(wrapper.find('.signers-count').exists()).toBe(false)
		})
	})

	describe('EDGE CASES: Handles various data scenarios', () => {
		it('handles missing displayName gracefully', () => {
			const wrapper = createWrapper({
				signersCount: 2,
				signers: [
					{ displayName: 'John Doe' },
					{ email: 'unknown@example.com' }, // No displayName
				],
			})

			const tooltip = wrapper.vm.tooltipContent
			expect(tooltip.content).toContain('John Doe')
			expect(tooltip.content).toContain('unknown@example.com')
		})

		it('handles large number of signers', () => {
			const signers = Array.from({ length: 50 }, (_, i) => ({
				displayName: `Signer ${i + 1}`,
			}))

			const wrapper = createWrapper({
				signersCount: 50,
				signers,
			})

			expect(wrapper.find('.signers-count').text()).toContain('50')
		})

		it('prioritizes signersCount over signers array length', () => {
			const wrapper = createWrapper({
				signersCount: 10,
				signers: [{ displayName: 'John' }], // Only 1 in array
			})

			expect(wrapper.find('.signers-count').text()).toContain('10')
		})
	})
})
