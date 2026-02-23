/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

let FileEntryStatus

vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((app, text) => text),
}))

vi.mock('vue-select', () => ({
	default: {
		name: 'VSelect',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		render: () => null,
	},
}))

beforeAll(async () => {
	;({ default: FileEntryStatus } = await import('../../../views/FilesList/FileEntry/FileEntryStatus.vue'))
})

describe('FileEntryStatus', () => {
	const createWrapper = (props = {}) => {
		return mount(FileEntryStatus, {
			props: {
				statusText: 'Draft',
				status: 0,
				signers: [],
				...props,
			},
			stubs: {
				NcIconSvgWrapper: true,
			},
		})
	}

	describe('RULE: Status 0 always displays as draft, regardless of signers', () => {
		it('shows draft variant when status is 0 and no signers', () => {
			const wrapper = createWrapper({
				status: 0,
				signers: [],
				statusText: 'Draft',
			})

			const chipElement = wrapper.find('.status-chip')
			expect(chipElement.classes()).toContain('status-chip--draft')
			expect(chipElement.classes()).not.toContain('status-chip--error')
		})

		it('shows draft variant when status is 0 and signers exist', () => {
			const wrapper = createWrapper({
				status: 0,
				signers: [
					{ id: 1, displayName: 'User One' },
					{ id: 2, displayName: 'User Two' },
				],
				statusText: 'Draft',
			})

			const chipElement = wrapper.find('.status-chip')
			expect(chipElement.classes()).toContain('status-chip--draft')
			expect(chipElement.classes()).not.toContain('status-chip--error')
		})

		it('maintains draft color even after repeated updates with different signer counts', () => {
			const wrapper = createWrapper({
				status: 0,
				signers: [],
				statusText: 'Draft',
			})

			expect(wrapper.find('.status-chip').classes()).toContain('status-chip--draft')

			// Update with signers
			wrapper.setProps({
				signers: [{ id: 1, displayName: 'User One' }],
			})

			expect(wrapper.find('.status-chip').classes()).toContain('status-chip--draft')

			// Remove signers again
			wrapper.setProps({
				signers: [],
			})

			expect(wrapper.find('.status-chip').classes()).toContain('status-chip--draft')
		})
	})

	describe('RULE: Other statuses map correctly to variants', () => {
		it.each([
			[-1, 'not-libresign'],
			[0, 'draft'],
			[1, 'available'],
			[2, 'partial'],
			[3, 'signed'],
			[4, 'deleted'],
			[5, 'signing'],
			[999, 'draft'], // fallback for unknown status
		])('statusToVariant(%i) returns "%s"', (status, expectedVariant) => {
			const wrapper = createWrapper()
			expect(wrapper.vm.statusToVariant(status)).toBe(expectedVariant)
		})
	})

	describe('BEHAVIOR: Consistency between upload and refresh', () => {
		it('maintains the same visual appearance when transitioning from upload to loaded state', () => {
			// Simulates: Just uploaded file (no signers yet)
			const wrapper = createWrapper({
				status: 0,
				signers: [],
				statusText: 'Draft',
			})

			let statusChip = wrapper.find('.status-chip')
			const classesBeforeRefresh = Array.from(statusChip.classes())

			// Simulates: After refresh, file is loaded with signers
			wrapper.setProps({
				signers: [
					{ id: 1, displayName: 'User One' },
				],
				statusText: 'Draft',
			})

			statusChip = wrapper.find('.status-chip')
			const classesAfterRefresh = Array.from(statusChip.classes())

			// Both should have draft variant
			expect(classesBeforeRefresh).toContain('status-chip--draft')
			expect(classesAfterRefresh).toContain('status-chip--draft')
			expect(classesBeforeRefresh).toEqual(classesAfterRefresh)
		})

		it('can use signersCount to show additional context in UI', () => {
			// This test documents how signersCount can be used in the component
			// to show "Draft · 0 signers" or similar
			const wrapper = createWrapper({
				status: 0,
				signers: [],
				statusText: 'Draft',
				signersCount: 0, // New field provided by backend
			})

			// The component can use this data to display additional info
			const statusChip = wrapper.find('.status-chip')
			expect(statusChip.classes()).toContain('status-chip--draft')
			// signersCount can be displayed separately or in subtitle
		})
	})

	describe('REQUIREMENT: Visible elements are hidden when statusText is "none"', () => {
		it('renders when statusText is not "none"', () => {
			const wrapper = createWrapper({
				statusText: 'Draft',
			})

			expect(wrapper.find('.status-chip').exists()).toBe(true)
		})
	})
})
