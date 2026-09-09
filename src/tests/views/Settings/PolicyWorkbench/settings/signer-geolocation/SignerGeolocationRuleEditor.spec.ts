/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import SignerGeolocationRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/signer-geolocation/SignerGeolocationRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

describe('SignerGeolocationRuleEditor.vue', () => {
	it('renders the three mode options including optional requester elevation copy', () => {
		const wrapper = mount(SignerGeolocationRuleEditor, {
			props: {
				modelValue: { mode: 'disabled' },
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						template: '<div class="radio-stub"><slot /></div>',
					},
				},
			},
		})

		expect(wrapper.findAll('.radio-stub')).toHaveLength(3)
		expect(wrapper.text()).toContain('Disabled')
		expect(wrapper.text()).toContain('Optional')
		expect(wrapper.text()).toContain('Required')
		expect(wrapper.text()).toContain('Requesters may require device location for selected signers')
	})

	it('emits a normalized optional value when optional is selected', async () => {
		const wrapper = mount(SignerGeolocationRuleEditor, {
			props: {
				modelValue: { mode: 'disabled' },
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						props: ['modelValue'],
						template: '<button class="radio-option" @click="$emit(\'update:modelValue\', true)"><slot /></button>',
					},
				},
			},
		})

		await wrapper.findAll('button')[1]?.trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toEqual({ mode: 'optional' })
	})

	it('ignores deselection events from the radio stubs', async () => {
		const wrapper = mount(SignerGeolocationRuleEditor, {
			props: {
				modelValue: { mode: 'required' },
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
