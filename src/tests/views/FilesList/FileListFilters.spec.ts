/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { computed, ref } from '@vue/reactivity'
import { setActivePinia } from 'pinia'
import { createTestingPinia } from '@pinia/testing'

import FileListFilters from '../../../views/FilesList/FileListFilters.vue'
import { useFiltersStore } from '../../../store/filters.js'

// Controlled ref to toggle isWide in tests
const mockIsWide = ref(true)

vi.mock('../../../composables/useFileListWidth.js', () => ({
	useFileListWidth: () => ({
		isWide: computed(() => mockIsWide.value),
		isMedium: computed(() => false),
		isNarrow: computed(() => !mockIsWide.value),
		width: ref(0),
	}),
}))

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
	default: {
		get: vi.fn(),
		post: vi.fn(),
		delete: vi.fn(),
		patch: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => `/ocs/v2.php${path}`),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, _key: string, defaultValue: unknown) => defaultValue),
}))

vi.mock('@nextcloud/moment', () => ({
	default: vi.fn(() => ({
		format: () => 'date',
		fromNow: () => '2 days ago',
	})),
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		name: 'NcButton',
		props: ['pressed', 'variant', 'ariaLabel'],
		template: '<button class="nc-button-stub" :data-pressed="pressed" :data-variant="variant"><slot /><slot name="icon" /></button>',
	},
}))

vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({
	default: {
		name: 'NcIconSvgWrapper',
		props: ['path', 'svg', 'size'],
		template: '<i class="nc-icon" :data-path="path" />',
	},
}))

vi.mock('@nextcloud/vue/components/NcPopover', () => ({
	default: {
		name: 'NcPopover',
		template: '<div class="nc-popover-stub"><slot name="trigger" /><slot /></div>',
	},
}))

vi.mock('../../../views/FilesList/FileListFilter/FileListFilterModified.vue', () => ({
	default: {
		name: 'FileListFilterModified',
		template: '<div class="file-list-filter-modified-stub" />',
	},
}))

vi.mock('../../../views/FilesList/FileListFilter/FileListFilterStatus.vue', () => ({
	default: {
		name: 'FileListFilterStatus',
		template: '<div class="file-list-filter-status-stub" />',
	},
}))

describe('FileListFilters.vue', () => {
	beforeEach(() => {
		setActivePinia(createTestingPinia({ createSpy: vi.fn }))
		vi.clearAllMocks()
		mockIsWide.value = true
	})

	function mountComponent() {
		return mount(FileListFilters)
	}

	it('has data-test-id="files-list-filters"', () => {
		const wrapper = mountComponent()
		expect(wrapper.find('[data-test-id="files-list-filters"]').exists()).toBe(true)
	})

	describe('wide layout (isWide = true)', () => {
		beforeEach(() => {
			mockIsWide.value = true
		})

		it('renders FileListFilterModified directly in the header', () => {
			const wrapper = mountComponent()
			expect(wrapper.find('.file-list-filter-modified-stub').exists()).toBe(true)
		})

		it('renders FileListFilterStatus directly in the header', () => {
			const wrapper = mountComponent()
			expect(wrapper.find('.file-list-filter-status-stub').exists()).toBe(true)
		})

		it('does not render the collapsed filter button', () => {
			const wrapper = mountComponent()
			expect(wrapper.find('.nc-popover-stub').exists()).toBe(false)
		})
	})

	describe('narrow layout (isWide = false)', () => {
		beforeEach(() => {
			mockIsWide.value = false
		})

		it('renders NcPopover as the collapsed filter trigger', () => {
			const wrapper = mountComponent()
			expect(wrapper.find('.nc-popover-stub').exists()).toBe(true)
		})

		it('renders the filter icon button inside the popover trigger', () => {
			const wrapper = mountComponent()
			const icon = wrapper.find('.nc-icon')
			expect(icon.exists()).toBe(true)
		})

		it('the filter button shows mdiFilterVariant icon', () => {
			const wrapper = mountComponent()
			const icon = wrapper.find('.nc-icon')
			expect(icon.attributes('data-path')).toBe(wrapper.vm.mdiFilterVariant)
		})

		it('renders individual filter components inside the popover content', () => {
			const wrapper = mountComponent()
			expect(wrapper.find('.file-list-filter-modified-stub').exists()).toBe(true)
			expect(wrapper.find('.file-list-filter-status-stub').exists()).toBe(true)
		})

		it('does not render individual filters outside of the popover', () => {
			const wrapper = mountComponent()
			// All filter stubs should be inside the popover
			const popover = wrapper.find('.nc-popover-stub')
			expect(popover.find('.file-list-filter-modified-stub').exists()).toBe(true)
			expect(popover.find('.file-list-filter-status-stub').exists()).toBe(true)
		})
	})

	describe('hasActiveFilters (button pressed state)', () => {
		beforeEach(() => {
			mockIsWide.value = false
		})

		it('button is not pressed when there are no active chips', () => {
			const filtersStore = useFiltersStore()
			filtersStore.$patch({ chips: {} })

			const wrapper = mountComponent()
			const button = wrapper.find('.nc-button-stub')
			expect(button.attributes('data-pressed')).toBe('false')
		})

		it('button is pressed when there are active chips', async () => {
			const filtersStore = useFiltersStore()
			filtersStore.$patch({ chips: { test: [{ text: 'Modified', icon: '', onclick: () => {} }] } })

			const wrapper = mountComponent()
			const button = wrapper.find('.nc-button-stub')
			expect(button.attributes('data-pressed')).toBe('true')
		})
	})
})
