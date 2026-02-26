/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestingPinia } from '@pinia/testing'

import FileListFilterChips from '../../../../views/FilesList/FileListFilter/FileListFilterChips.vue'
import { useFiltersStore } from '../../../../store/filters.js'

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
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

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn(), patch: vi.fn() },
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => `/ocs/v2.php${path}`),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, _key: string, defaultValue: unknown) => defaultValue),
}))

vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
	subscribe: vi.fn(),
}))

vi.mock('@nextcloud/vue/components/NcAvatar', () => ({
	default: {
		name: 'NcAvatar',
		props: ['user', 'size', 'disableMenu', 'verboseStatus'],
		template: '<span class="nc-avatar-stub" :data-user="user" />',
	},
}))

vi.mock('@nextcloud/vue/components/NcChip', () => ({
	default: {
		name: 'NcChip',
		props: ['text', 'iconSvg', 'ariaLabelClose'],
		emits: ['close'],
		template: '<span class="nc-chip-stub" :data-text="text" @click.stop><slot name="icon" /><button class="nc-chip-close" @click="$emit(\'close\')" /></span>',
	},
}))

describe('FileListFilterChips.vue', () => {
	beforeEach(() => {
		setActivePinia(createTestingPinia({ createSpy: vi.fn }))
	})

	function mountComponent() {
		return mount(FileListFilterChips)
	}

	it('renders nothing when there are no active chips', () => {
		const wrapper = mountComponent()
		expect(wrapper.find('ul').exists()).toBe(false)
	})

	it('renders a chip for each active chip in the store', async () => {
		const filtersStore = useFiltersStore()
		filtersStore.$patch({
			chips: {
				status: [
					{ id: 1, text: 'Signed', onclick: () => {} },
					{ id: 2, text: 'Ready to sign', onclick: () => {} },
				],
			},
		})

		const wrapper = mountComponent()
		const chips = wrapper.findAll('.nc-chip-stub')
		expect(chips).toHaveLength(2)
	})

	it('passes the chip text to NcChip', async () => {
		const filtersStore = useFiltersStore()
		filtersStore.$patch({
			chips: {
				modified: [{ id: 'today', text: 'Today', onclick: () => {} }],
			},
		})

		const wrapper = mountComponent()
		const chip = wrapper.find('.nc-chip-stub')
		expect(chip.attributes('data-text')).toBe('Today')
	})

	it('renders chips from multiple filter categories combined', async () => {
		const filtersStore = useFiltersStore()
		filtersStore.$patch({
			chips: {
				status: [{ id: 1, text: 'Signed', onclick: () => {} }],
				modified: [{ id: 'today', text: 'Today', onclick: () => {} }],
			},
		})

		const wrapper = mountComponent()
		expect(wrapper.findAll('.nc-chip-stub')).toHaveLength(2)
	})

	it('reactively shows chips when store updates after mount', async () => {
		const filtersStore = useFiltersStore()
		const wrapper = mountComponent()

		// Initially empty — no ul rendered
		expect(wrapper.find('ul').exists()).toBe(false)

		// Add a chip
		filtersStore.$patch({
			chips: { status: [{ id: 1, text: 'Signed', onclick: () => {} }] },
		})
		await wrapper.vm.$nextTick()

		expect(wrapper.find('ul').exists()).toBe(true)
		expect(wrapper.findAll('.nc-chip-stub')).toHaveLength(1)
	})

	it('reactively hides chips when store becomes empty after mount', async () => {
		const filtersStore = useFiltersStore()
		filtersStore.$patch({
			chips: { status: [{ id: 1, text: 'Signed', onclick: () => {} }] },
		})

		const wrapper = mountComponent()
		expect(wrapper.find('ul').exists()).toBe(true)

		// Clear chips — use callback to replace (not merge) the chips object
		filtersStore.$patch((state: typeof filtersStore.$state) => { state.chips = {} })
		await wrapper.vm.$nextTick()

		expect(wrapper.find('ul').exists()).toBe(false)
	})
})
