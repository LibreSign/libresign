/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import FilesListTableHeaderButton from '../../../views/FilesList/FilesListTableHeaderButton.vue'

const filesSortingStoreMock = {
	sortingMode: 'created_at',
	sortingDirection: 'desc',
	toggleSortBy: vi.fn(),
}

vi.mock('../../../store/filesSorting.js', () => ({
	useFilesSortingStore: vi.fn(() => filesSortingStoreMock),
}))

describe('FilesListTableHeaderButton.vue', () => {
	beforeEach(() => {
		filesSortingStoreMock.sortingMode = 'created_at'
		filesSortingStoreMock.sortingDirection = 'desc'
		filesSortingStoreMock.toggleSortBy.mockReset()
	})

	function createWrapper(mode = 'size') {
		return mount(FilesListTableHeaderButton, {
			props: {
				name: 'Size',
				mode,
			},
			global: {
				stubs: {
					NcButton: {
						name: 'NcButton',
						props: ['alignment', 'title', 'variant'],
						template: '<button><slot /><slot name="icon" /></button>',
					},
					NcIconSvgWrapper: true,
				},
			},
		})
	}

	it('computes ascending state when sorting by another column', () => {
		const wrapper = createWrapper('size')

		expect(wrapper.vm.isAscending).toBe(true)
	})

	it('computes descending state when the same column is sorted descending', () => {
		filesSortingStoreMock.sortingMode = 'size'
		filesSortingStoreMock.sortingDirection = 'desc'
		const wrapper = createWrapper('size')

		expect(wrapper.vm.isAscending).toBe(false)
	})

	it('passes end alignment for the size column', () => {
		const wrapper = createWrapper('size')

		expect(wrapper.findComponent({ name: 'NcButton' }).props('alignment')).toBe('end')
	})

	it('triggers the store sort toggle for the current mode', async () => {
		const wrapper = createWrapper('size')

		await wrapper.find('button').trigger('click')

		expect(filesSortingStoreMock.toggleSortBy).toHaveBeenCalledWith('size')
	})
})