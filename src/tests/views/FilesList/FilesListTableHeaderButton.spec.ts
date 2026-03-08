/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import FilesListTableHeaderButton from '../../../views/FilesList/FilesListTableHeaderButton.vue'
import { useFilesSortingStore } from '../../../store/filesSorting.js'

vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
	subscribe: vi.fn(),
}))

vi.mock('@nextcloud/logger', () => ({
	getLogger: vi.fn(() => ({
		error: vi.fn(),
		warn: vi.fn(),
		info: vi.fn(),
		debug: vi.fn(),
	})),
	getLoggerBuilder: vi.fn(() => ({
		setApp: vi.fn().mockReturnThis(),
		detectUser: vi.fn().mockReturnThis(),
		build: vi.fn(() => ({
			error: vi.fn(),
			warn: vi.fn(),
			info: vi.fn(),
			debug: vi.fn(),
		})),
	})),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({ uid: 'testuser', displayName: 'Test User' })),
}))

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => `/ocs/v2.php${path}`),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, _key: string, defaultValue: unknown) => defaultValue),
}))

const NcButtonStub = {
	name: 'NcButton',
	props: ['alignment', 'title', 'variant'],
	emits: ['click'],
	template: '<button :class="$attrs.class" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
}

function createWrapper(mode = 'size', name = 'Size') {
	return mount(FilesListTableHeaderButton, {
		props: { name, mode },
		global: {
			stubs: {
				NcButton: NcButtonStub,
				NcIconSvgWrapper: true,
			},
		},
	})
}

describe('FilesListTableHeaderButton.vue', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	it('computes ascending state when sorting by another column', () => {
		const wrapper = createWrapper('size')

		expect(wrapper.vm.isAscending).toBe(true)
	})

	it('computes descending state when the same column is sorted descending', () => {
		const sortingStore = useFilesSortingStore()
		sortingStore.sortingMode = 'size'
		sortingStore.sortingDirection = 'desc'

		const wrapper = createWrapper('size')
		expect(wrapper.vm.isAscending).toBe(false)
	})

	it('passes end alignment for the size column', () => {
		const wrapper = createWrapper('size')

		expect(wrapper.findComponent({ name: 'NcButton' }).props('alignment')).toBe('end')
	})

	it('triggers the store sort toggle for the current mode', async () => {
		const sortingStore = useFilesSortingStore()
		const spy = vi.spyOn(sortingStore, 'toggleSortBy')
		const wrapper = createWrapper('size')

		await wrapper.find('button').trigger('click')

		expect(spy).toHaveBeenCalledWith('size')
	})

	describe('Vue 3 sorting interactions', () => {
		it('is true when the column is active and direction is asc', () => {
			const sortingStore = useFilesSortingStore()
			sortingStore.sortingMode = 'created_at'
			sortingStore.sortingDirection = 'asc'

			const wrapper = createWrapper('created_at', 'Created at')
			expect(wrapper.vm.isAscending).toBe(true)
		})

		it('reacts when direction changes from asc to desc', async () => {
			const sortingStore = useFilesSortingStore()
			sortingStore.sortingMode = 'created_at'
			sortingStore.sortingDirection = 'asc'

			const wrapper = createWrapper('created_at', 'Created at')
			expect(wrapper.vm.isAscending).toBe(true)

			sortingStore.sortingDirection = 'desc'
			await wrapper.vm.$nextTick()

			expect(wrapper.vm.isAscending).toBe(false)
		})

		it('adds the active class when the column is the active sort mode', () => {
			const sortingStore = useFilesSortingStore()
			sortingStore.sortingMode = 'created_at'

			const wrapper = createWrapper('created_at', 'Created at')
			expect(wrapper.find('button').classes()).toContain('files-list__column-sort-button--active')
		})

		it('does not add the active class when another column is active', () => {
			const wrapper = createWrapper('status', 'Status')

			expect(wrapper.find('button').classes()).not.toContain('files-list__column-sort-button--active')
		})

		it('removes the active class when the active mode changes', async () => {
			const sortingStore = useFilesSortingStore()
			sortingStore.sortingMode = 'created_at'

			const wrapper = createWrapper('created_at', 'Created at')
			expect(wrapper.find('button').classes()).toContain('files-list__column-sort-button--active')

			sortingStore.sortingMode = 'status'
			await wrapper.vm.$nextTick()

			expect(wrapper.find('button').classes()).not.toContain('files-list__column-sort-button--active')
		})

		it('delegates click to toggleSortBy for the provided mode', async () => {
			const sortingStore = useFilesSortingStore()
			const spy = vi.spyOn(sortingStore, 'toggleSortBy')
			const wrapper = createWrapper('created_at', 'Created at')

			await wrapper.find('button').trigger('click')

			expect(spy).toHaveBeenCalledWith('created_at')
		})
	})
})
