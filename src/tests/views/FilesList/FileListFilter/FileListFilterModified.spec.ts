/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestingPinia } from '@pinia/testing'

import FileListFilterModified from '../../../../views/FilesList/FileListFilter/FileListFilterModified.vue'
import { useFiltersStore } from '../../../../store/filters.js'

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string, vars?: Record<string, unknown>) => {
		if (vars) {
			return Object.entries(vars).reduce(
				(acc, [key, val]) => acc.replace(`{${key}}`, String(val)),
				text
			)
		}
		return text
	}),
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

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		name: 'NcButton',
		props: ['variant', 'alignment', 'wide', 'pressed'],
		emits: ['click'],
		template: '<button class="nc-button-stub" :data-variant="variant" :data-pressed="pressed" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
	},
}))

vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({
	default: {
		name: 'NcIconSvgWrapper',
		props: ['path', 'svg', 'size'],
		template: '<i class="nc-icon" />',
	},
}))

vi.mock('../../../../views/FilesList/FileListFilter/FileListFilter.vue', () => ({
	default: {
		name: 'FileListFilter',
		props: ['isActive', 'filterName'],
		emits: ['reset-filter'],
		template: '<div class="file-list-filter-stub" :data-is-active="isActive"><slot /><slot name="icon" /></div>',
	},
}))

describe('FileListFilterModified.vue', () => {
	beforeEach(() => {
		setActivePinia(createTestingPinia({ createSpy: vi.fn }))
	})

	function mountComponent() {
		return mount(FileListFilterModified)
	}

	/** Finds a preset option button by its visible label */
	function findPresetButton(wrapper: ReturnType<typeof mountComponent>, label: string) {
		return wrapper.findAll('.nc-button-stub').find((b) => b.text() === label)
	}

	it('selectedOption is null when filter_modified is not set', () => {
		const wrapper = mountComponent()
		expect(wrapper.vm.selectedOption).toBeNull()
	})

	it('isActive is false when no preset is selected', () => {
		const wrapper = mountComponent()
		expect(wrapper.vm.isActive).toBe(false)
	})

	it('passes isActive=false to FileListFilter when no preset selected', () => {
		const wrapper = mountComponent()
		expect(wrapper.find('.file-list-filter-stub').attributes('data-is-active')).toBe('false')
	})

	it('renders 5 preset option buttons', () => {
		const wrapper = mountComponent()
		const buttons = wrapper.findAll('.nc-button-stub')
		expect(buttons).toHaveLength(5)
	})

	it('clicking a preset button sets selectedOption to that preset id', async () => {
		const wrapper = mountComponent()
		await findPresetButton(wrapper, 'Today')!.trigger('click')
		expect(wrapper.vm.selectedOption).toBe('today')
	})

	it('isActive becomes true after a preset is selected', async () => {
		const wrapper = mountComponent()
		await findPresetButton(wrapper, 'Today')!.trigger('click')
		expect(wrapper.vm.isActive).toBe(true)
	})

	it('selected button reports pressed=true', async () => {
		const wrapper = mountComponent()
		const todayButton = findPresetButton(wrapper, 'Today')!
		await todayButton.trigger('click')
		expect(todayButton.attributes('data-pressed')).toBe('true')
	})

	it('clicking the same preset again deselects it (radio toggle)', async () => {
		const wrapper = mountComponent()
		const todayButton = findPresetButton(wrapper, 'Today')!
		await todayButton.trigger('click')
		expect(wrapper.vm.selectedOption).toBe('today')
		await todayButton.trigger('click')
		expect(wrapper.vm.selectedOption).toBeNull()
	})

	it('isActive goes back to false after deselecting', async () => {
		const wrapper = mountComponent()
		const todayButton = findPresetButton(wrapper, 'Today')!
		await todayButton.trigger('click')
		await todayButton.trigger('click')
		expect(wrapper.vm.isActive).toBe(false)
	})

	it('initialises selectedOption from filtersStore.filter_modified when set', () => {
		const filtersStore = useFiltersStore()
		filtersStore.$patch({ filter_modified: 'last-7' })

		const wrapper = mountComponent()
		expect(wrapper.vm.selectedOption).toBe('last-7')
	})

	it('resetFilter clears selectedOption when it is set', async () => {
		const wrapper = mountComponent()
		await findPresetButton(wrapper, 'Last 7 days')!.trigger('click')
		expect(wrapper.vm.selectedOption).toBe('last-7')

		wrapper.vm.resetFilter()
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.selectedOption).toBeNull()
	})

	// createTestingPinia auto-spies all store actions — no vi.spyOn needed
	it('watch triggers onFilterUpdateChips when selectedOption changes', async () => {
		const filtersStore = useFiltersStore()
		const wrapper = mountComponent()
		await findPresetButton(wrapper, 'Today')!.trigger('click')
		expect(filtersStore.onFilterUpdateChips).toHaveBeenCalled()
	})
})
