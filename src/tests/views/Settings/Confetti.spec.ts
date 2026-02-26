/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

const loadStateMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
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

let Confetti: unknown

beforeAll(async () => {
	;({ default: Confetti } = await import('../../../views/Settings/Confetti.vue'))
})

describe('Confetti', () => {
	beforeEach(() => {
		loadStateMock.mockReset()
	})

	it('defaults to true when state is not set', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => fallback)

		const wrapper = mount(Confetti as never, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcCheckboxRadioSwitch: { template: '<div><slot /></div>' },
				},
			},
		})

		expect(wrapper.vm.showConfetti).toBe(true)
	})

	it('reads show_confetti_after_signing from initial state', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'show_confetti_after_signing') return true
			return fallback
		})

		const wrapper = mount(Confetti as never, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcCheckboxRadioSwitch: { template: '<div><slot /></div>' },
				},
			},
		})

		expect(wrapper.vm.showConfetti).toBe(true)
	})

	it('calls OCP.AppConfig.setValue with "1" when enabled', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => fallback)
		const setValueMock = vi.fn()
		vi.stubGlobal('OCP', { AppConfig: { setValue: setValueMock } })

		const wrapper = mount(Confetti as never, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcCheckboxRadioSwitch: { template: '<div><slot /></div>' },
				},
			},
		})

		await wrapper.setData({ showConfetti: true })
		wrapper.vm.saveShowConfetti()

		expect(setValueMock).toHaveBeenCalledWith('libresign', 'show_confetti_after_signing', '1')
	})

	it('calls OCP.AppConfig.setValue with "0" when disabled', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'show_confetti_after_signing') return true
			return fallback
		})
		const setValueMock = vi.fn()
		vi.stubGlobal('OCP', { AppConfig: { setValue: setValueMock } })

		const wrapper = mount(Confetti as never, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcCheckboxRadioSwitch: { template: '<div><slot /></div>' },
				},
			},
		})

		await wrapper.setData({ showConfetti: false })
		wrapper.vm.saveShowConfetti()

		expect(setValueMock).toHaveBeenCalledWith('libresign', 'show_confetti_after_signing', '0')
	})
})
