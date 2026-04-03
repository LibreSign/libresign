/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { interpolateL10n } from '../../../testHelpers/l10n.js'
import { mount } from '@vue/test-utils'

import FileEntryCheckbox from '../../../../views/FilesList/FileEntry/FileEntryCheckbox.vue'

const filesStoreMock = {
	ordered: [1, 2, 3, 4],
}

const keyboardStoreMock = {
	shiftKey: false,
}

const selectionStoreMock = {
	selected: [] as Array<number | string>,
	lastSelectedIndex: null as number | null,
	lastSelection: [] as Array<number | string>,
	set: vi.fn(),
	setLastIndex: vi.fn(),
	reset: vi.fn(),
}

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n({
	t: (_app: string, text: string, vars?: Record<string, string | number>) => interpolateL10n(text, vars),
	n: (_app: string, singular: string, plural: string, count: number, vars?: Record<string, string | number>) => {
		const template = count === 1 ? singular : plural
		return interpolateL10n(template, { count, ...(vars ?? {}) })
	},
	translate: (_app: string, text: string, vars?: Record<string, string | number>) => interpolateL10n(text, vars),
	translatePlural: (_app: string, singular: string, plural: string, count: number, vars?: Record<string, string | number>) => {
		const template = count === 1 ? singular : plural
		return interpolateL10n(template, { count, ...(vars ?? {}) })
	},
}))

vi.mock('../../../../logger.js', () => ({
	default: {
		debug: vi.fn(),
	},
}))

vi.mock('../../../../store/files.js', () => ({
	useFilesStore: vi.fn(() => filesStoreMock),
}))

vi.mock('../../../../store/keyboard.js', () => ({
	useKeyboardStore: vi.fn(() => keyboardStoreMock),
}))

vi.mock('../../../../store/selection.js', () => ({
	useSelectionStore: vi.fn(() => selectionStoreMock),
}))

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: {
		modelValue: {
			type: Boolean,
			default: false,
		},
		ariaLabel: String,
	},
	emits: ['update:modelValue'],
	template: '<input type="checkbox" :checked="modelValue" :aria-label="ariaLabel" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
}

const NcLoadingIconStub = {
	name: 'NcLoadingIcon',
	props: ['name'],
	template: '<span class="loading-icon" />',
}

function createWrapper(overrides: Record<string, unknown> = {}) {
	return mount(FileEntryCheckbox, {
		props: {
			source: {
				id: 3,
				basename: 'contract.pdf',
			},
			...overrides,
		},
		global: {
			stubs: {
				NcLoadingIcon: NcLoadingIconStub,
				NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
			},
		},
	})
}

describe('FileEntryCheckbox.vue', () => {
	beforeEach(() => {
		filesStoreMock.ordered = [1, 2, 3, 4]
		keyboardStoreMock.shiftKey = false
		selectionStoreMock.selected = []
		selectionStoreMock.lastSelectedIndex = null
		selectionStoreMock.lastSelection = []
		selectionStoreMock.set.mockReset()
		selectionStoreMock.setLastIndex.mockReset()
		selectionStoreMock.reset.mockReset()
	})

	it('computes aria label from the source basename', () => {
		const wrapper = createWrapper()

		expect(wrapper.vm.ariaLabel).toBe('Toggle selection for file "contract.pdf"')
	})

	it('adds the file id to the selection on a regular selection change', () => {
		const wrapper = createWrapper()

		wrapper.vm.onSelectionChange(true)

		expect(selectionStoreMock.set).toHaveBeenCalledWith([3])
		expect(selectionStoreMock.setLastIndex).toHaveBeenCalledWith(2)
	})

	it('extends the selection range when shift is pressed', () => {
		keyboardStoreMock.shiftKey = true
		selectionStoreMock.lastSelectedIndex = 1
		selectionStoreMock.lastSelection = [2]
		const wrapper = createWrapper()

		wrapper.vm.onSelectionChange(true)

		expect(selectionStoreMock.set).toHaveBeenCalledWith([2, 3])
		expect(selectionStoreMock.setLastIndex).not.toHaveBeenCalled()
	})

	it('resets the selection when escape is handled', () => {
		const wrapper = createWrapper()

		wrapper.vm.resetSelection()

		expect(selectionStoreMock.reset).toHaveBeenCalledTimes(1)
	})

	describe('Vue 3 checkbox bindings', () => {
		it('renders an unchecked checkbox when the file is not selected', () => {
			const wrapper = createWrapper()
			const checkbox = wrapper.find('input[type="checkbox"]')

			expect(checkbox.exists()).toBe(true)
			expect((checkbox.element as HTMLInputElement).checked).toBe(false)
		})

		it('passes modelValue to NcCheckboxRadioSwitch', () => {
			const wrapper = createWrapper()
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)

			expect(stub.props('modelValue')).toBe(false)
		})

		it('handles update:modelValue emitted by the checkbox stub', async () => {
			const wrapper = createWrapper()
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)

			await stub.vm.$emit('update:modelValue', true)

			expect(selectionStoreMock.set).toHaveBeenCalledWith([3])
			expect(selectionStoreMock.setLastIndex).toHaveBeenCalledWith(2)
		})

		it('shows the loading icon instead of the checkbox while loading', () => {
			const wrapper = createWrapper({ isLoading: true })

			expect(wrapper.find('.loading-icon').exists()).toBe(true)
			expect(wrapper.findComponent(NcCheckboxRadioSwitchStub).exists()).toBe(false)
		})

		it('shows the checkbox when loading is false', () => {
			const wrapper = createWrapper({ isLoading: false })

			expect(wrapper.find('.loading-icon').exists()).toBe(false)
			expect(wrapper.findComponent(NcCheckboxRadioSwitchStub).exists()).toBe(true)
		})
	})
})
