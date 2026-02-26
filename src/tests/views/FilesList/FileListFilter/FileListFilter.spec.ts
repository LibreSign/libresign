/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestingPinia } from '@pinia/testing'

import FileListFilter from '../../../../views/FilesList/FileListFilter/FileListFilter.vue'

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

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		name: 'NcButton',
		props: ['variant', 'alignment', 'wide'],
		emits: ['click'],
		template: '<button class="nc-button-stub" :data-variant="variant" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
	},
}))

vi.mock('@nextcloud/vue/components/NcPopover', () => ({
	default: {
		name: 'NcPopover',
		template: '<div class="nc-popover-stub"><slot name="trigger" /><slot /></div>',
	},
}))

describe('FileListFilter.vue', () => {
	beforeEach(() => {
		setActivePinia(createTestingPinia({ createSpy: vi.fn }))
	})

	function mountComponent(props: Record<string, unknown> = {}) {
		return mount(FileListFilter, {
			props: {
				isActive: false,
				filterName: 'Test Filter',
				...props,
			},
			slots: {
				default: '<div class="filter-content-stub" />',
				icon: '<i class="filter-icon-stub" />',
			},
		})
	}

	/** Finds a button stub by its visible text label */
	function findButton(wrapper: ReturnType<typeof mountComponent>, label: string) {
		return wrapper.findAll('.nc-button-stub').find((b) => b.text().includes(label))
	}

	it('renders filterName in the trigger button', () => {
		const wrapper = mountComponent()
		expect(wrapper.text()).toContain('Test Filter')
	})

	it('uses tertiary variant on the trigger button when isActive is false', () => {
		const wrapper = mountComponent({ isActive: false })
		expect(findButton(wrapper, 'Test Filter')?.attributes('data-variant')).toBe('tertiary')
	})

	it('uses secondary variant on the trigger button when isActive is true', () => {
		const wrapper = mountComponent({ isActive: true })
		expect(findButton(wrapper, 'Test Filter')?.attributes('data-variant')).toBe('secondary')
	})

	it('does not render the Clear filter button when isActive is false', () => {
		const wrapper = mountComponent({ isActive: false })
		expect(findButton(wrapper, 'Clear filter')).toBeUndefined()
	})

	it('renders the Clear filter button when isActive is true', () => {
		const wrapper = mountComponent({ isActive: true })
		expect(findButton(wrapper, 'Clear filter')).toBeDefined()
	})

	it('renders the slot content inside the popover', () => {
		const wrapper = mountComponent()
		const popover = wrapper.find('.nc-popover-stub')
		expect(popover.find('.filter-content-stub').exists()).toBe(true)
	})

	it('emits reset-filter when the Clear filter button is clicked', async () => {
		const wrapper = mountComponent({ isActive: true })
		await findButton(wrapper, 'Clear filter')!.trigger('click')
		expect(wrapper.emitted('reset-filter')).toHaveLength(1)
	})
})
