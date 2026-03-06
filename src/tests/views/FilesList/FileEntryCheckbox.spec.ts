/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import FileEntryCheckbox from '../../../views/FilesList/FileEntry/FileEntryCheckbox.vue'

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

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string, params?: Record<string, string>) => {
		if (!params) {
			return text
		}

		return Object.entries(params).reduce((message, [key, value]) => {
			return message.replace(`{${key}}`, value)
		}, text)
	}),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('../../../logger.js', () => ({
	default: {
		debug: vi.fn(),
	},
}))

vi.mock('../../../store/files.js', () => ({
	useFilesStore: vi.fn(() => filesStoreMock),
}))

vi.mock('../../../store/keyboard.js', () => ({
	useKeyboardStore: vi.fn(() => keyboardStoreMock),
}))

vi.mock('../../../store/selection.js', () => ({
	useSelectionStore: vi.fn(() => selectionStoreMock),
}))

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

	function createWrapper() {
		return mount(FileEntryCheckbox, {
			props: {
				source: {
					id: 3,
					basename: 'contract.pdf',
				},
			},
			global: {
				stubs: {
					NcLoadingIcon: true,
					NcCheckboxRadioSwitch: true,
				},
			},
		})
	}

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
})
