/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import DrawInkColorRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/draw-ink-color/DrawInkColorRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const colorPickerStub = {
	props: ['modelValue'],
	template: '<div class="picker-stub" :data-value="modelValue"><button class="picker-pick" @click="$emit(\'update:modelValue\', \'#1F2A5A\')" /><slot /></div>',
}

function mountEditor(modelValue: string | null) {
	return mount(DrawInkColorRuleEditor, {
		props: { modelValue },
		global: { stubs: { NcColorPicker: colorPickerStub } },
	})
}

describe('DrawInkColorRuleEditor.vue', () => {
	it('shows the current color', () => {
		const wrapper = mountEditor('#1f2a5a')

		expect(wrapper.text()).toContain('Default ink color for drawn signatures')
		expect(wrapper.find('.picker-stub').attributes('data-value')).toBe('#1f2a5a')
	})

	it('falls back to black for an invalid value', () => {
		const wrapper = mountEditor('unexpected')

		expect(wrapper.find('.picker-stub').attributes('data-value')).toBe('#000000')
	})

	it('emits the picked color normalized to lowercase', async () => {
		const wrapper = mountEditor('#000000')

		await wrapper.find('.picker-pick').trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe('#1f2a5a')
	})
})
