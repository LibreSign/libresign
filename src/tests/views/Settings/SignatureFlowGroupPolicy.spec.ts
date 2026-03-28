/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../testHelpers/l10n.js'
import { flushPromises, mount } from '@vue/test-utils'
import SignatureFlowGroupPolicy from '../../../views/Settings/SignatureFlowGroupPolicy.vue'

type GroupRow = {
	id: string
	displayname: string
}

type SignatureFlowGroupPolicyVm = {
	enabled: boolean
	allowChildOverride: boolean
	selectedFlow?: { value: string } | null
	onToggleChange: () => Promise<void>
	onAllowChildOverrideChange: () => Promise<void>
	onFlowChange: () => Promise<void>
}

const axiosGetMock = vi.fn()
const generateOcsUrlMock = vi.fn((path: string) => path)
const confirmPasswordMock = vi.fn(() => Promise.resolve())

const fetchGroupPolicyMock = vi.fn()
const saveGroupPolicyMock = vi.fn()
const clearGroupPolicyMock = vi.fn()

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: (...args: unknown[]) => axiosGetMock(...args),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (...args: unknown[]) => generateOcsUrlMock(...(args as [string])),
}))

vi.mock('@nextcloud/password-confirmation', () => ({
	confirmPassword: () => confirmPasswordMock(),
}))

vi.mock('@nextcloud/l10n', () => createL10nMock())

vi.mock('../../../store/policies', () => ({
	usePoliciesStore: () => ({
		fetchGroupPolicy: (...args: unknown[]) => fetchGroupPolicyMock(...args),
		saveGroupPolicy: (...args: unknown[]) => saveGroupPolicyMock(...args),
		clearGroupPolicy: (...args: unknown[]) => clearGroupPolicyMock(...args),
	}),
}))

describe('SignatureFlowGroupPolicy', () => {
	beforeEach(() => {
		axiosGetMock.mockReset()
		generateOcsUrlMock.mockClear()
		confirmPasswordMock.mockClear()
		fetchGroupPolicyMock.mockReset()
		saveGroupPolicyMock.mockReset()
		clearGroupPolicyMock.mockReset()

		axiosGetMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						groups: [
							{ id: 'finance', displayname: 'Finance' },
							{ id: 'legal', displayname: 'Legal' },
						],
					},
				},
			},
		})
	})

	function createWrapper() {
		return mount(SignatureFlowGroupPolicy, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcSelect: {
						name: 'NcSelect',
						props: ['modelValue'],
						emits: ['update:modelValue', 'search-change'],
						template: '<div class="nc-select-stub" />',
					},
					NcCheckboxRadioSwitch: {
						name: 'NcCheckboxRadioSwitch',
						props: ['modelValue', 'value', 'type'],
						emits: ['update:modelValue'],
						template: '<button class="checkbox-radio-switch-stub" @click="$emit(\'update:modelValue\', type === \'radio\' ? value : !modelValue)"><slot /></button>',
					},
					NcLoadingIcon: true,
					NcSavingIndicatorIcon: true,
					NcNoteCard: true,
				},
			},
		})
	}

	it('loads a selected group policy from the store', async () => {
		fetchGroupPolicyMock.mockResolvedValue({
			policyKey: 'signature_flow',
			scope: 'group',
			targetId: 'finance',
			value: 'ordered_numeric',
			allowChildOverride: false,
			visibleToChild: true,
			allowedValues: ['parallel', 'ordered_numeric'],
		})

		const wrapper = createWrapper()
		await flushPromises()

		const select = wrapper.findComponent({ name: 'NcSelect' })
		select.vm.$emit('update:modelValue', { id: 'finance', displayname: 'Finance' } satisfies GroupRow)
		await flushPromises()

		expect(fetchGroupPolicyMock).toHaveBeenCalledWith('finance', 'signature_flow')
		expect((wrapper.vm as unknown as SignatureFlowGroupPolicyVm).enabled).toBe(true)
		expect((wrapper.vm as unknown as SignatureFlowGroupPolicyVm).selectedFlow?.value).toBe('ordered_numeric')
		expect((wrapper.vm as unknown as SignatureFlowGroupPolicyVm).allowChildOverride).toBe(false)
	})

	it('saves the selected group override through the policy store', async () => {
		fetchGroupPolicyMock.mockResolvedValue(null)
		saveGroupPolicyMock.mockResolvedValue({
			policyKey: 'signature_flow',
			scope: 'group',
			targetId: 'finance',
			value: 'parallel',
			allowChildOverride: true,
			visibleToChild: true,
			allowedValues: ['parallel', 'ordered_numeric'],
		})

		const wrapper = createWrapper()
		await flushPromises()

		const select = wrapper.findComponent({ name: 'NcSelect' })
		select.vm.$emit('update:modelValue', { id: 'finance', displayname: 'Finance' } satisfies GroupRow)
		await flushPromises()

		const vm = wrapper.vm as unknown as SignatureFlowGroupPolicyVm
		vm.enabled = true
		vm.allowChildOverride = true
		await vm.onToggleChange()
		await flushPromises()

		expect(confirmPasswordMock).toHaveBeenCalledTimes(1)
		expect(saveGroupPolicyMock).toHaveBeenCalledWith('finance', 'signature_flow', 'parallel', true)
	})

	it('clears the selected group override when disabled', async () => {
		fetchGroupPolicyMock.mockResolvedValue({
			policyKey: 'signature_flow',
			scope: 'group',
			targetId: 'finance',
			value: 'parallel',
			allowChildOverride: true,
			visibleToChild: true,
			allowedValues: ['parallel', 'ordered_numeric'],
		})
		clearGroupPolicyMock.mockResolvedValue({
			policyKey: 'signature_flow',
			scope: 'group',
			targetId: 'finance',
			value: null,
			allowChildOverride: true,
			visibleToChild: true,
			allowedValues: [],
		})

		const wrapper = createWrapper()
		await flushPromises()

		const select = wrapper.findComponent({ name: 'NcSelect' })
		select.vm.$emit('update:modelValue', { id: 'finance', displayname: 'Finance' } satisfies GroupRow)
		await flushPromises()

		const vm = wrapper.vm as unknown as SignatureFlowGroupPolicyVm
		vm.enabled = false
		await vm.onToggleChange()
		await flushPromises()

		expect(confirmPasswordMock).toHaveBeenCalledTimes(1)
		expect(clearGroupPolicyMock).toHaveBeenCalledWith('finance', 'signature_flow')
	})
})
