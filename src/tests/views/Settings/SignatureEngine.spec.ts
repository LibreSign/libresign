/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import SignatureEngine from '../../../views/Settings/SignatureEngine.vue'

const loadStateMock = vi.fn()
const emitMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/event-bus', () => ({
	emit: (...args: unknown[]) => emitMock(...args),
}))

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

const OCP = {
	AppConfig: {
		setValue: vi.fn(),
	},
}

;(globalThis as typeof globalThis & { OCP: typeof OCP }).OCP = OCP

describe('SignatureEngine.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		loadStateMock.mockImplementation((_app: string, _key: string, fallback: unknown) => fallback)
	})

	function createWrapper() {
		return mount(SignatureEngine, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcSelect: true,
				},
			},
		})
	}

	it('maps the initial state to the selected option', () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'signature_engine') return 'PhpNative'
			return fallback
		})

		const wrapper = createWrapper()

		expect(wrapper.vm.selectedOption).toEqual({ id: 'PhpNative', label: 'Native' })
	})

	it('updates the selected engine through the computed setter', () => {
		const wrapper = createWrapper()

		wrapper.vm.selectedOption = { id: 'PhpNative', label: 'Native' }

		expect(wrapper.vm.selectedEngineId).toBe('PhpNative')
	})

	it('persists the engine and emits the change event on success', async () => {
		OCP.AppConfig.setValue.mockImplementation((_app: string, _key: string, _value: string, callbacks: { success: () => void }) => callbacks.success())
		const wrapper = createWrapper()

		await wrapper.vm.saveEngine({ id: 'PhpNative', label: 'Native' })

		expect(OCP.AppConfig.setValue).toHaveBeenCalledWith('libresign', 'signature_engine', 'PhpNative', expect.any(Object))
		expect(emitMock).toHaveBeenCalledWith('libresign:signature-engine:changed', 'PhpNative')
	})
})