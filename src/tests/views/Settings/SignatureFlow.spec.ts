/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

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

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	isRTL: vi.fn(() => false),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
}))

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
		loadStateMock.mockReset()
		generateOcsUrlMock.mockClear()
		axiosPostMock.mockClear()
	})

	it('uses boolean switch payload before saving', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'signature_flow') return 'parallel'
			return fallback
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

		wrapper.vm.enabled = false
		wrapper.vm.onToggleChange()
		await flushPromises()

		expect(axiosPostMock).toHaveBeenCalled()
		const lastCall = axiosPostMock.mock.calls[axiosPostMock.mock.calls.length - 1] as [string, { enabled: boolean }]
		expect(lastCall[1].enabled).toBe(false)
	})

	it('loads backend mode and persists selected radio mode', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'signature_flow') return 'ordered_numeric'
			return fallback
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
		await flushPromises()

		expect(wrapper.vm.selectedFlow?.value).toBe('ordered_numeric')

		const radioAndSwitchButtons = wrapper.findAll('.checkbox-radio-switch-stub')
		await radioAndSwitchButtons[1].trigger('click')
		await flushPromises()

		expect(wrapper.vm.selectedFlow?.value).toBe('parallel')
		const lastCall = axiosPostMock.mock.calls[axiosPostMock.mock.calls.length - 1] as [string, { mode: string }]
		expect(lastCall[1].mode).toBe('parallel')
	})
})
