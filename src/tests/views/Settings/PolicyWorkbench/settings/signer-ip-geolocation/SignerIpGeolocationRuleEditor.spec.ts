/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import SignerIpGeolocationRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/signer-ip-geolocation/SignerIpGeolocationRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

describe('SignerIpGeolocationRuleEditor.vue', () => {
	it('renders disabled and enabled options without a requester override', () => {
		const wrapper = mount(SignerIpGeolocationRuleEditor, {
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

		expect(wrapper.findAll('.radio-stub')).toHaveLength(2)
		expect(wrapper.text()).toContain('Disabled')
		expect(wrapper.text()).toContain('Enabled')
		expect(wrapper.text()).toContain('locally configured GeoIP database')
		expect(wrapper.text()).not.toContain('Optional')
		expect(wrapper.text()).not.toContain('Required')
	})

	it('emits a normalized enabled value when enabled is selected', async () => {
		const wrapper = mount(SignerIpGeolocationRuleEditor, {
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

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toEqual({ mode: 'enabled' })
	})

	it('ignores deselection events from the radio stubs', async () => {
		const wrapper = mount(SignerIpGeolocationRuleEditor, {
			props: {
				modelValue: { mode: 'enabled' },
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
