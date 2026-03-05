/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Unit tests for FilesListTableHeaderButton:
 * - isAscending computed: determines which arrow icon is shown
 * - --active CSS class: applied only to the column currently being sorted
 * - click: delegates to filesSortingStore.toggleSortBy(mode)
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import FilesListTableHeaderButton from './FilesListTableHeaderButton.vue'
import { useFilesSortingStore } from '../../store/filesSorting.js'

// ---------------------------------------------------------------------------
// Mocks (same set required by filesSorting store)
// ---------------------------------------------------------------------------

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
	generateOcsUrl: vi.fn((path) => `/ocs/v2.php${path}`),
}))

// loadState returns the default value — store initialises with
// sortingMode = 'created_at' and sortingDirection = 'desc'.
vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app, _key, defaultValue) => defaultValue),
}))

// ---------------------------------------------------------------------------
// NcButton stub — forwards $attrs so @click reaches the root button element
// ---------------------------------------------------------------------------
const NcButtonStub = {
	name: 'NcButton',
	template: '<button v-bind="$attrs"><slot name="icon" /><slot /></button>',
}

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------
const createWrapper = (mode = 'created_at') =>
	mount(FilesListTableHeaderButton, {
		props: { name: 'Created at', mode },
		global: {
			stubs: {
				NcButton: NcButtonStub,
				NcIconSvgWrapper: { template: '<span />' },
			},
		},
	})

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('FilesListTableHeaderButton', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	describe('RULE: isAscending reflects store state', () => {
		it('is true when the column is not the active sort mode', () => {
			const wrapper = createWrapper('status') // store defaults to 'created_at'
			const vm = wrapper.vm as InstanceType<typeof FilesListTableHeaderButton> & { isAscending: boolean }

			expect(vm.isAscending).toBe(true)
		})

		it('is true when the column is active and direction is asc', () => {
			const sortingStore = useFilesSortingStore()
			sortingStore.sortingMode = 'created_at'
			sortingStore.sortingDirection = 'asc'

			const wrapper = createWrapper('created_at')
			const vm = wrapper.vm as InstanceType<typeof FilesListTableHeaderButton> & { isAscending: boolean }

			expect(vm.isAscending).toBe(true)
		})

		it('is false when the column is active and direction is desc', () => {
			const sortingStore = useFilesSortingStore()
			sortingStore.sortingMode = 'created_at'
			sortingStore.sortingDirection = 'desc'

			const wrapper = createWrapper('created_at')
			const vm = wrapper.vm as InstanceType<typeof FilesListTableHeaderButton> & { isAscending: boolean }

			expect(vm.isAscending).toBe(false)
		})

		it('becomes false reactively when direction changes to desc', async () => {
			const sortingStore = useFilesSortingStore()
			sortingStore.sortingMode = 'created_at'
			sortingStore.sortingDirection = 'asc'

			const wrapper = createWrapper('created_at')
			const vm = wrapper.vm as InstanceType<typeof FilesListTableHeaderButton> & { isAscending: boolean }
			expect(vm.isAscending).toBe(true)

			sortingStore.sortingDirection = 'desc'
			await wrapper.vm.$nextTick()

			expect(vm.isAscending).toBe(false)
		})
	})

	describe('RULE: --active class applied only to the active column', () => {
		it('adds --active class when the column is the active sort mode', () => {
			const sortingStore = useFilesSortingStore()
			sortingStore.sortingMode = 'created_at'

			const wrapper = createWrapper('created_at')
			expect(wrapper.find('button').classes()).toContain('files-list__column-sort-button--active')
		})

		it('does not add --active class when the column is not the active sort mode', () => {
			// Store defaults to 'created_at'; mount with mode='status'
			const wrapper = createWrapper('status')
			expect(wrapper.find('button').classes()).not.toContain('files-list__column-sort-button--active')
		})

		it('removes --active class reactively when another column becomes active', async () => {
			const sortingStore = useFilesSortingStore()
			sortingStore.sortingMode = 'created_at'

			const wrapper = createWrapper('created_at')
			expect(wrapper.find('button').classes()).toContain('files-list__column-sort-button--active')

			sortingStore.sortingMode = 'status'
			await wrapper.vm.$nextTick()

			expect(wrapper.find('button').classes()).not.toContain('files-list__column-sort-button--active')
		})
	})

	describe('RULE: click delegates to filesSortingStore.toggleSortBy(mode)', () => {
		it('calls toggleSortBy with the column mode on click', async () => {
			const sortingStore = useFilesSortingStore()
			const spy = vi.spyOn(sortingStore, 'toggleSortBy')

			const wrapper = createWrapper('created_at')
			await wrapper.find('button').trigger('click')

			expect(spy).toHaveBeenCalledOnce()
			expect(spy).toHaveBeenCalledWith('created_at')
		})

		it('calls toggleSortBy with the correct mode for each column', async () => {
			const sortingStore = useFilesSortingStore()
			const spy = vi.spyOn(sortingStore, 'toggleSortBy')

			const wrapper = createWrapper('status')
			await wrapper.find('button').trigger('click')

			expect(spy).toHaveBeenCalledWith('status')
		})
	})
})
