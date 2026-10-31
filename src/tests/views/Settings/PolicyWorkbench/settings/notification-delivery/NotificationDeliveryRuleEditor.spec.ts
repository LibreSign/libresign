/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NotificationDeliveryRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/notification-delivery/NotificationDeliveryRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

function mountEditor(value: unknown) {
	return mount(NotificationDeliveryRuleEditor, {
		props: { modelValue: value as never },
	})
}

describe('NotificationDeliveryRuleEditor', () => {
	it('shows sync as default and explains that async timing depends on job runners', () => {
		const wrapper = mountEditor(null)
		const radios = wrapper.findAll('input[type="radio"]')
		expect(radios).toHaveLength(2)
		expect((radios[0].element as HTMLInputElement).checked).toBe(true)
		expect(wrapper.text()).toContain('Synchronous')
		expect(wrapper.text()).toContain('Asynchronous')
		expect(wrapper.text()).toContain('Delivery timing depends on the available job runners.')
	})

	it('selects and emits the async mode', async () => {
		const wrapper = mountEditor('sync')
		wrapper.findAllComponents(NcCheckboxRadioSwitch)[1].vm.$emit('update:modelValue', true)
		await wrapper.vm.$nextTick()
		expect(wrapper.emitted('update:modelValue'), JSON.stringify(wrapper.emitted())).toEqual([['async']])
	})

	it('selects and emits explicit sync mode', async () => {
		const wrapper = mountEditor('async')
		const radios = wrapper.findAll('input[type="radio"]')
		expect((radios[1].element as HTMLInputElement).checked).toBe(true)
		wrapper.findAllComponents(NcCheckboxRadioSwitch)[0].vm.$emit('update:modelValue', true)
		await wrapper.vm.$nextTick()
		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe('sync')
	})
})
