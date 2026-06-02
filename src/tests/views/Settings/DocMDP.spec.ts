/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../testHelpers/l10n.js'
import { flushPromises, mount } from '@vue/test-utils'

type DocMDPLevel = {
	value: number
	label: string
	description: string
}

type DocMDPVm = {
	enabled: boolean
	selectedLevel?: DocMDPLevel
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

vi.mock('@nextcloud/l10n', () => createL10nMock())

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
		loadStateMock.mockReset()
		generateOcsUrlMock.mockClear()
		axiosPostMock.mockClear()
	})

	it('uses typed backend config on load', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'docmdp_config') {
				return {
					enabled: false,
					defaultLevel: 2,
					availableLevels: [
						{ value: 1, label: 'L1', description: 'D1' },
						{ value: 2, label: 'L2', description: 'D2' },
					],
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

		expect(vm.enabled).toBe(false)
		expect(vm.selectedLevel?.value).toBe(2)
	})

	it('respects backend default config when storage is empty', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'docmdp_config') {
				return {
					enabled: true,
					defaultLevel: 2,
					availableLevels: [
						{ value: 0, label: 'L0', description: 'D0' },
						{ value: 1, label: 'L1', description: 'D1' },
						{ value: 2, label: 'L2', description: 'D2' },
					],
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

	it('changes selected level and persists selected radio value', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'docmdp_config') {
				return {
					enabled: true,
					defaultLevel: 1,
					availableLevels: [
						{ value: 1, label: 'L1', description: 'D1' },
						{ value: 2, label: 'L2', description: 'D2' },
						{ value: 3, label: 'L3', description: 'D3' },
					],
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

		const radioAndSwitchButtons = wrapper.findAll('.checkbox-radio-switch-stub')
		await radioAndSwitchButtons[3].trigger('click')
		await flushPromises()

		expect(vm.selectedLevel?.value).toBe(3)
		expect(axiosPostMock).toHaveBeenCalled()
		const lastCall = axiosPostMock.mock.calls[axiosPostMock.mock.calls.length - 1] as [string, { enabled: boolean, defaultLevel: number }]
		expect(lastCall[1].defaultLevel).toBe(3)
	})

	it('uses preferred level 2 when enabling without explicit selected level', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'docmdp_config') {
				return {
					enabled: false,
					defaultLevel: 2,
					availableLevels: [
						{ value: 0, label: 'L0', description: 'D0' },
						{ value: 1, label: 'L1', description: 'D1' },
						{ value: 2, label: 'L2', description: 'D2' },
					],
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

		expect(vm.selectedLevel?.value).toBe(2)
		vm.enabled = true
		vm.onEnabledChange()
		await flushPromises()

		const lastCall = axiosPostMock.mock.calls[axiosPostMock.mock.calls.length - 1] as [string, { enabled: boolean, defaultLevel: number }]
		expect(lastCall[1]).toMatchObject({ enabled: true, defaultLevel: 2 })
	})
})
