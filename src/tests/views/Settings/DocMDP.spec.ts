/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

type DocMDPLevel = {
	value: number
	label: string
	description: string
}

type DocMDPVm = {
	enabled: boolean
	selectedLevel?: DocMDPLevel
	canEdit?: boolean
	onEnabledChange: () => void
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

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

let DocMDP: unknown

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'value', 'type'],
	emits: ['update:modelValue'],
	template: '<button class="checkbox-radio-switch-stub" @click="$emit(\'update:modelValue\', type === \'radio\' ? value : !modelValue)"><slot /></button>',
}

beforeAll(async () => {
	;({ default: DocMDP } = await import('../../../views/Settings/DocMDP.vue'))
})

describe('DocMDP', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		loadStateMock.mockReset()
		generateOcsUrlMock.mockClear()
		axiosPostMock.mockClear()
	})

	it('loads effective docmdp policy from bootstrap state', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'effective_policies') {
				return {
					policies: {
						docmdp: {
							policyKey: 'docmdp',
							effectiveValue: 2,
							allowedValues: [0, 1, 2, 3],
							sourceScope: 'system',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: false,
							canUseAsRequestOverride: false,
							preferenceWasCleared: false,
							blockedBy: null,
						},
					},
				}
			}
			return fallback
		})

		const wrapper = mount(DocMDP as never, {
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
		const vm = wrapper.vm as unknown as DocMDPVm
		await flushPromises()

		expect(vm.enabled).toBe(true)
		expect(vm.selectedLevel?.value).toBe(2)
	})

	it('disables controls when effective policy is not editable', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'effective_policies') {
				return {
					policies: {
						docmdp: {
							policyKey: 'docmdp',
							effectiveValue: 1,
							allowedValues: [1],
							sourceScope: 'group',
							visible: true,
							editableByCurrentActor: false,
							canSaveAsUserDefault: false,
							canUseAsRequestOverride: false,
							preferenceWasCleared: false,
							blockedBy: 'group',
						},
					},
				}
			}
			return fallback
		})

		const wrapper = mount(DocMDP as never, {
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
		const vm = wrapper.vm as unknown as DocMDPVm
		await flushPromises()

		expect(vm.enabled).toBe(true)
		expect(vm.selectedLevel?.value).toBe(1)
		expect(vm.canEdit).toBe(false)
	})

	it('changes selected level and persists to system policy endpoint', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'effective_policies') {
				return {
					policies: {
						docmdp: {
							policyKey: 'docmdp',
							effectiveValue: 1,
							allowedValues: [0, 1, 2, 3],
							sourceScope: 'system',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: false,
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
							policyKey: 'docmdp',
							effectiveValue: 3,
							allowedValues: [0, 1, 2, 3],
							sourceScope: 'system',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: false,
							canUseAsRequestOverride: false,
							preferenceWasCleared: false,
							blockedBy: null,
						},
					},
				},
			},
		})

		const wrapper = mount(DocMDP as never, {
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
		const vm = wrapper.vm as unknown as DocMDPVm
		await flushPromises()

		const radioAndSwitchButtons = wrapper.findAll('.checkbox-radio-switch-stub')
		await radioAndSwitchButtons[3].trigger('click')
		await flushPromises()

		expect(vm.selectedLevel?.value).toBe(3)
		expect(axiosPostMock).toHaveBeenCalled()
		const lastCall = axiosPostMock.mock.calls[axiosPostMock.mock.calls.length - 1] as [string, { value: number }]
		expect(lastCall[0]).toBe('/apps/libresign/api/v1/policies/system/docmdp')
		expect(lastCall[1].value).toBe(3)
	})

	it('saves value 0 when disabling docmdp', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'effective_policies') {
				return {
					policies: {
						docmdp: {
							policyKey: 'docmdp',
							effectiveValue: 2,
							allowedValues: [0, 1, 2, 3],
							sourceScope: 'system',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: false,
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
							policyKey: 'docmdp',
							effectiveValue: 0,
							allowedValues: [0, 1, 2, 3],
							sourceScope: 'system',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: false,
							canUseAsRequestOverride: false,
							preferenceWasCleared: false,
							blockedBy: null,
						},
					},
				},
			},
		})

		const wrapper = mount(DocMDP as never, {
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
		const vm = wrapper.vm as unknown as DocMDPVm
		await flushPromises()

		vm.enabled = false
		vm.onEnabledChange()
		await flushPromises()

		const lastCall = axiosPostMock.mock.calls[axiosPostMock.mock.calls.length - 1] as [string, { value: number }]
		expect(lastCall[0]).toBe('/apps/libresign/api/v1/policies/system/docmdp')
		expect(lastCall[1].value).toBe(0)
	})
})
