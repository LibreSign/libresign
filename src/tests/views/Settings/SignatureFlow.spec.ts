/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../testHelpers/l10n.js'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

type SignatureFlowOption = {
	value: string
}

type SignatureFlowVm = {
	enabled: boolean
	selectedFlow?: SignatureFlowOption
	onToggleChange: () => void
}

const loadStateMock = vi.fn()
const generateOcsUrlMock = vi.fn((path: string) => path)
const axiosPostMock = vi.fn((..._args: unknown[]) => Promise.resolve({ data: { ocs: { data: {} } } }))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (...args: unknown[]) => generateOcsUrlMock(...(args as [string])),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: axiosPostMock,
	},
}))

vi.mock('@nextcloud/l10n', () => createL10nMock())

let SignatureFlow: unknown

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'value', 'type'],
	emits: ['update:modelValue'],
	template: '<button class="checkbox-radio-switch-stub" @click="$emit(\'update:modelValue\', type === \'radio\' ? value : !modelValue)"><slot /></button>',
}

beforeAll(async () => {
	;({ default: SignatureFlow } = await import('../../../views/Settings/SignatureFlow.vue'))
})

describe('SignatureFlow', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		loadStateMock.mockReset()
		generateOcsUrlMock.mockClear()
		axiosPostMock.mockClear()
	})

	it('uses boolean switch payload before saving', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'effective_policies') {
				return {
					policies: {
						signature_flow: {
							policyKey: 'signature_flow',
							effectiveValue: 'parallel',
							allowedValues: ['none', 'parallel', 'ordered_numeric'],
							sourceScope: 'system',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: true,
							canUseAsRequestOverride: false,
							preferenceWasCleared: false,
							blockedBy: null,
						},
					},
				}
			}
			return fallback
		})
		axiosPostMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						policy: {
							policyKey: 'signature_flow',
							effectiveValue: 'none',
							allowedValues: ['none', 'parallel', 'ordered_numeric'],
							sourceScope: 'system',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: true,
							canUseAsRequestOverride: true,
							preferenceWasCleared: false,
							blockedBy: null,
						},
					},
				},
			},
		})

		const wrapper = mount(SignatureFlow as never, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcLoadingIcon: true,
					NcNoteCard: true,
					NcSavingIndicatorIcon: true,
				},
			},
		})
		const vm = wrapper.vm as unknown as SignatureFlowVm

		vm.enabled = false
		vm.onToggleChange()
		await flushPromises()

		expect(axiosPostMock).toHaveBeenCalled()
		const lastCall = axiosPostMock.mock.calls[axiosPostMock.mock.calls.length - 1] as [string, { enabled: boolean }]
		expect(lastCall[0]).toBe('/apps/libresign/api/v1/policies/system/signature_flow')
		expect(lastCall[1]).toEqual({ value: 'none' })
	})

	it('loads backend mode and persists selected radio mode', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'effective_policies') {
				return {
					policies: {
						signature_flow: {
							policyKey: 'signature_flow',
							effectiveValue: 'ordered_numeric',
							allowedValues: ['none', 'parallel', 'ordered_numeric'],
							sourceScope: 'system',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: true,
							canUseAsRequestOverride: false,
							preferenceWasCleared: false,
							blockedBy: null,
						},
					},
				}
			}
			return fallback
		})
		axiosPostMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						policy: {
							policyKey: 'signature_flow',
							effectiveValue: 'parallel',
							allowedValues: ['none', 'parallel', 'ordered_numeric'],
							sourceScope: 'system',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: true,
							canUseAsRequestOverride: false,
							preferenceWasCleared: false,
							blockedBy: null,
						},
					},
				},
			},
		})

		const wrapper = mount(SignatureFlow as never, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcLoadingIcon: true,
					NcNoteCard: true,
					NcSavingIndicatorIcon: true,
				},
			},
		})
		const vm = wrapper.vm as unknown as SignatureFlowVm
		await flushPromises()

		expect(vm.selectedFlow?.value).toBe('ordered_numeric')

		const radioAndSwitchButtons = wrapper.findAll('.checkbox-radio-switch-stub')
		await radioAndSwitchButtons[1].trigger('click')
		await flushPromises()

		expect(vm.selectedFlow?.value).toBe('parallel')
		const lastCall = axiosPostMock.mock.calls[axiosPostMock.mock.calls.length - 1] as [string, { value: string }]
		expect(lastCall[0]).toBe('/apps/libresign/api/v1/policies/system/signature_flow')
		expect(lastCall[1].value).toBe('parallel')
	})
})
