/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import CollectMetadataRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/collect-metadata/CollectMetadataRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

describe('CollectMetadataRuleEditor.vue', () => {
	it('renders the two radio options with their explanatory copy', () => {
		const wrapper = mount(CollectMetadataRuleEditor, {
			props: {
				modelValue: true,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						template: '<div class="radio-stub"><slot /></div>',
					},
				},
			},
		})

		expect(wrapper.findAll('.radio-stub')).toHaveLength(2)
		expect(wrapper.text()).toContain('Collect signer metadata')
		expect(wrapper.text()).toContain('Disable metadata collection')
		expect(wrapper.text()).toContain('Store signer IP address and browser information in signing metadata.')
		expect(wrapper.text()).toContain('Do not store signer IP address or browser information in signing metadata.')
	})

	it('emits true when the enabled option is selected', async () => {
		const wrapper = mount(CollectMetadataRuleEditor, {
			props: {
				modelValue: false,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						props: ['modelValue'],
						template: '<button class="radio-on" @click="$emit(\'update:modelValue\', true)"><slot /></button>',
					},
				},
			},
		})

		await wrapper.find('.radio-on').trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe(true)
	})

	it('emits false when the disabled option is selected', async () => {
		const wrapper = mount(CollectMetadataRuleEditor, {
			props: {
				modelValue: true,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						props: ['modelValue'],
						template: '<button class="radio-off" @click="$emit(\'update:modelValue\', true)"><slot /></button>',
					},
				},
			},
		})

		await wrapper.findAll('button')[1]?.trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe(false)
	})

	it('ignores deselection events from the radio stubs', async () => {
		const wrapper = mount(CollectMetadataRuleEditor, {
			props: {
				modelValue: true,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						props: ['modelValue'],
						template: '<button class="radio-ignore" @click="$emit(\'update:modelValue\', false)"><slot /></button>',
					},
				},
			},
		})

		await wrapper.find('.radio-ignore').trigger('click')

		expect(wrapper.emitted('update:modelValue')).toBeUndefined()
	})
})
