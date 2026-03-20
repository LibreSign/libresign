/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../testHelpers/l10n.js'

vi.mock('@nextcloud/l10n', () => createL10nMock())
vi.mock('../../../store/policies', () => ({
	usePoliciesStore: () => ({
		getPolicy: (key: string) => {
			if (key === 'signature_flow') {
				return { effectiveValue: 'ordered_numeric' }
			}
			return null
		},
		fetchEffectivePolicies: vi.fn().mockResolvedValue(undefined),
		saveSystemPolicy: vi.fn().mockResolvedValue(undefined),
		saveGroupPolicy: vi.fn().mockResolvedValue(undefined),
		saveUserPreference: vi.fn().mockResolvedValue(undefined),
	}),
}))

import RealPolicyWorkbench from '../../../views/Settings/PolicyWorkbench/RealPolicyWorkbench.vue'

describe('RealPolicyWorkbench.vue', () => {
	it('shows signing order with sophisticated visual interface: filter, toggle, counts, and scopes', () => {
		const wrapper = mount(RealPolicyWorkbench, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcTextField: { template: '<div><label>Find setting</label><input type="text" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>' },
					NcButton: { template: '<button><slot /></button>' },
					NcIconSvgWrapper: { template: '' },
					NcNoteCard: { template: '<div class="note-card"><slot /></div>' },
					NcDialog: { template: '<div class="dialog"><slot /></div>' },
					NcCheckboxRadioSwitch: { template: '<input type="checkbox" @change="$emit(\'update:modelValue\', $event.target.checked)" />' },
					NcSelectUsers: { template: '<div class="nc-select-users-stub" />' },
					PolicyRuleCard: { template: '<div class="rule-card"><slot /></div>' },
					SignatureFlow: { template: '<div class="signature-flow-stub" />' },
				},
			},
		})

		const text = wrapper.text()

		// Validate search/filter UI exists
		expect(wrapper.find('input[type="text"]').exists()).toBe(true)
		expect(text).toContain('Find setting')

		// Validate settings count display
		expect(text).toContain('1 of 1 settings visible')

		// Validate toggle button exists for card/list view
		expect(wrapper.find('.policy-workbench__catalog-view-button').exists()).toBe(true)

		// Validate signing order is displayed
		expect(text).toContain('Signing order')
		expect(text).toContain('Define whether signers work in parallel or in a sequential order')
		expect(text).toContain('Define the default signing flow and where overrides are allowed')

		// Validate default value is shown
		expect(text).toContain('Sequential')
		expect(text).toContain('Global default:')

		// Validate counts shown
		expect(text).toContain('Group overrides: 0')
		expect(text).toContain('User overrides: 0')

		// Validate POC settings are NOT present
		expect(text).not.toContain('Confetti')
		expect(text).not.toContain('Identification factors')
	})
})

