/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import FileEntryStatus from '../../../views/FilesList/FileEntry/FileEntryStatus.vue'

function mountStatus(props: { status: number; statusText: string; signers?: unknown[] }) {
	return mount(FileEntryStatus, {
		props: {
			signers: [],
			...props,
		},
	})
}

describe('FileEntryStatus.vue', () => {
	it('renders nothing when statusText is "none"', () => {
		const wrapper = mountStatus({ status: 0, statusText: 'none' })
		expect(wrapper.find('.status-chip').exists()).toBe(false)
	})

	it('renders chip when statusText is not "none"', () => {
		const wrapper = mountStatus({ status: 3, statusText: 'Signed' })
		expect(wrapper.find('.status-chip').exists()).toBe(true)
	})

	it('displays the statusText inside the chip', () => {
		const wrapper = mountStatus({ status: 3, statusText: 'Signed' })
		expect(wrapper.find('.status-chip__text').text()).toBe('Signed')
	})

	it.each([
		{ status: -1, expected: 'status-chip--not-libresign' },
		{ status: 0, expected: 'status-chip--draft' },
		{ status: 1, expected: 'status-chip--available' },
		{ status: 2, expected: 'status-chip--partial' },
		{ status: 3, expected: 'status-chip--signed' },
		{ status: 4, expected: 'status-chip--deleted' },
		{ status: 5, expected: 'status-chip--signing' },
	])('applies variant class "$expected" for status $status', ({ status, expected }) => {
		const wrapper = mountStatus({ status, statusText: 'any' })
		expect(wrapper.find('.status-chip').classes()).toContain(expected)
	})

	it('falls back to draft variant for unknown status', () => {
		const wrapper = mountStatus({ status: 99, statusText: 'Unknown' })
		expect(wrapper.find('.status-chip').classes()).toContain('status-chip--draft')
	})

	it('chip does not apply any inline style that would override the nowrap fix', () => {
		const wrapper = mountStatus({ status: 3, statusText: 'Signed' })
		const chip = wrapper.find('.status-chip')
		// white-space is controlled by scoped CSS (nowrap); no inline style should override it
		expect(chip.exists()).toBe(true)
		expect(chip.attributes('style')).toBeUndefined()
	})
})
