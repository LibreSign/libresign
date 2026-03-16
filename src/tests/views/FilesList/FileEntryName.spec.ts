/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../testHelpers/l10n.js'
import { mount } from '@vue/test-utils'

import FileEntryName from '../../../views/FilesList/FileEntry/FileEntryName.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

describe('FileEntryName.vue', () => {
	function createWrapper(props = {}) {
		return mount(FileEntryName, {
			props: {
				basename: 'contract',
				extension: '.pdf',
				...props,
			},
			global: {
				stubs: {
					NcTextField: {
						name: 'NcTextField',
						props: ['modelValue', 'label', 'autofocus', 'minlength', 'maxlength', 'required', 'enterkeyhint'],
						emits: ['update:modelValue', 'blur', 'keyup.esc'],
						template: '<div class="text-field-stub"><input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
					},
				},
			},
		})
	}

	it('renders the basename and extension when not renaming', () => {
		const wrapper = createWrapper()

		expect(wrapper.find('.files-list__row-name-text').text()).toBe('contract.pdf')
	})

	it('starts renaming and emits the renaming state', async () => {
		const wrapper = createWrapper()

		wrapper.vm.startRenaming()
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.isRenaming).toBe(true)
		expect(wrapper.vm.newName).toBe('contract')
		expect(wrapper.emitted('renaming')).toEqual([[true]])
	})

	it('stops renaming and clears the draft name', () => {
		const wrapper = createWrapper()

		wrapper.vm.isRenaming = true
		wrapper.vm.newName = 'draft'
		wrapper.vm.stopRenaming()

		expect(wrapper.vm.isRenaming).toBe(false)
		expect(wrapper.vm.newName).toBe('')
		expect(wrapper.emitted('renaming')).toEqual([[false]])
	})

	it('emits the trimmed name when it changes', async () => {
		const wrapper = createWrapper()

		wrapper.vm.newName = ' updated-name '
		await wrapper.vm.onRename()

		expect(wrapper.emitted('rename')).toEqual([['updated-name']])
	})
})
