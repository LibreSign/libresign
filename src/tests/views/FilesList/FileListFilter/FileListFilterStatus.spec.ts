/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestingPinia } from '@pinia/testing'

import FileListFilterStatus from '../../../../views/FilesList/FileListFilter/FileListFilterStatus.vue'
import { useFiltersStore } from '../../../../store/filters.js'
import { FILE_STATUS } from '../../../../constants.js'

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

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		name: 'NcButton',
		props: ['variant', 'alignment', 'wide', 'pressed'],
		emits: ['click'],
		template: '<button class="nc-button-stub" :data-pressed="pressed" :data-variant="variant" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
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

describe('FileListFilterStatus.vue', () => {
	beforeEach(() => {
		setActivePinia(createTestingPinia({ createSpy: vi.fn }))
	})

	function mountComponent() {
		return mount(FileListFilterStatus)
	}

	/** Finds a status option button by its visible label */
	function findStatusButton(wrapper: ReturnType<typeof mountComponent>, label: string) {
		return wrapper.findAll('.nc-button-stub').find((b) => b.text().includes(label))
	}

	it('renders one button for each file status option', () => {
		const wrapper = mountComponent()
		// DRAFT, ABLE_TO_SIGN, PARTIAL_SIGNED, SIGNED = 4
		expect(wrapper.findAll('.nc-button-stub')).toHaveLength(4)
	})

	it('isActive is false when no options are selected', () => {
		const wrapper = mountComponent()
		expect(wrapper.vm.isActive).toBe(false)
	})

	it('passes isActive=false to FileListFilter when nothing selected', () => {
		const wrapper = mountComponent()
		expect(wrapper.find('.file-list-filter-stub').attributes('data-is-active')).toBe('false')
	})

	it('all buttons are unpressed when no options are selected', () => {
		const wrapper = mountComponent()
		wrapper.findAll('.nc-button-stub').forEach(button => {
			expect(button.attributes('data-pressed')).toBe('false')
		})
	})

	it('clicking a status button adds it to selectedOptions', async () => {
		const wrapper = mountComponent()
		await findStatusButton(wrapper, 'Draft')!.trigger('click')
		expect(wrapper.vm.selectedOptions).toContain(FILE_STATUS.DRAFT)
	})

	it('isActive becomes true after a status is selected', async () => {
		const wrapper = mountComponent()
		await findStatusButton(wrapper, 'Draft')!.trigger('click')
		expect(wrapper.vm.isActive).toBe(true)
	})

	it('clicked button becomes pressed', async () => {
		const wrapper = mountComponent()
		const draftButton = findStatusButton(wrapper, 'Draft')!
		await draftButton.trigger('click')
		expect(draftButton.attributes('data-pressed')).toBe('true')
	})

	it('clicking a second different status adds it too (multi-select)', async () => {
		const wrapper = mountComponent()
		await findStatusButton(wrapper, 'Draft')!.trigger('click')
		await findStatusButton(wrapper, 'Ready to sign')!.trigger('click')
		expect(wrapper.vm.selectedOptions).toContain(FILE_STATUS.DRAFT)
		expect(wrapper.vm.selectedOptions).toContain(FILE_STATUS.ABLE_TO_SIGN)
	})

	it('clicking an already-selected option removes it (toggle)', async () => {
		const wrapper = mountComponent()
		const draftButton = findStatusButton(wrapper, 'Draft')!
		await draftButton.trigger('click') // select
		expect(wrapper.vm.selectedOptions).toContain(FILE_STATUS.DRAFT)
		await draftButton.trigger('click') // deselect
		expect(wrapper.vm.selectedOptions).not.toContain(FILE_STATUS.DRAFT)
	})

	it('isActive goes back to false when all selections are removed', async () => {
		const wrapper = mountComponent()
		const draftButton = findStatusButton(wrapper, 'Draft')!
		await draftButton.trigger('click')
		await draftButton.trigger('click')
		expect(wrapper.vm.isActive).toBe(false)
	})

	it('initialises selectedOptions from filtersStore.filterStatusArray', () => {
		const filtersStore = useFiltersStore()
		filtersStore.$patch({ filter_status: `[${FILE_STATUS.SIGNED}]` })

		const wrapper = mountComponent()
		expect(wrapper.vm.selectedOptions).toContain(FILE_STATUS.SIGNED)
	})

	it('resetFilter clears all selectedOptions', async () => {
		const wrapper = mountComponent()
		await findStatusButton(wrapper, 'Draft')!.trigger('click')
		await findStatusButton(wrapper, 'Ready to sign')!.trigger('click')
		expect(wrapper.vm.selectedOptions).toHaveLength(2)

		wrapper.vm.resetFilter()
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.selectedOptions).toHaveLength(0)
	})

	// createTestingPinia auto-spies all store actions — no vi.spyOn needed
	it('watch calls onFilterUpdateChipsAndSave on the store when selectedOptions changes', async () => {
		const filtersStore = useFiltersStore()
		const wrapper = mountComponent()
		await findStatusButton(wrapper, 'Draft')!.trigger('click')
		expect(filtersStore.onFilterUpdateChipsAndSave).toHaveBeenCalled()
	})
})
