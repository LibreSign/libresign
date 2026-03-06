/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import axios from '@nextcloud/axios'

import SigningMode from '../../../views/Settings/SigningMode.vue'

const loadStateMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
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

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

const OCP = {
	AppConfig: {
		setValue: vi.fn(),
	},
}

;(globalThis as typeof globalThis & { OCP: typeof OCP }).OCP = OCP

describe('SigningMode.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		loadStateMock.mockImplementation((_app: string, _key: string, fallback: unknown) => fallback)
	})

	function createWrapper() {
		return mount(SigningMode, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcNoteCard: true,
					NcCheckboxRadioSwitch: true,
					NcLoadingIcon: true,
					NcSavingIndicatorIcon: true,
					NcTextField: true,
				},
			},
		})
	}

	it('loads async mode and worker configuration from initial state', () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'signing_mode') return 'async'
			if (key === 'worker_type') return 'external'
			if (key === 'parallel_workers') return '8'
			return fallback
		})

		const wrapper = createWrapper()

		expect(wrapper.vm.asyncEnabled).toBe(true)
		expect(wrapper.vm.externalWorkerEnabled).toBe(true)
		expect(wrapper.vm.parallelWorkersCount).toBe('8')
		expect(wrapper.vm.lastSavedParallelWorkers).toBe('8')
	})

	it('persists signing mode changes through the OCS endpoint', async () => {
		vi.mocked(axios.post).mockResolvedValue({})
		const wrapper = createWrapper()

		await wrapper.vm.onToggleChange(true)

		expect(axios.post).toHaveBeenCalledWith('apps/libresign/api/v1/admin/signing-mode/config', {
			mode: 'async',
			workerType: 'local',
		})
		expect(wrapper.vm.loading).toBe(false)
	})

	it('restores the last valid workers count when the input is invalid', () => {
		const wrapper = createWrapper()

		wrapper.vm.lastSavedParallelWorkers = '4'
		wrapper.vm.parallelWorkersCount = '99'
		wrapper.vm.saveParallelWorkers()

		expect(OCP.AppConfig.setValue).not.toHaveBeenCalled()
		expect(wrapper.vm.parallelWorkersCount).toBe('4')
	})

	it('saves parallel workers through OCP.AppConfig when the value changes', () => {
		const wrapper = createWrapper()

		wrapper.vm.parallelWorkersCount = '6'
		wrapper.vm.saveParallelWorkers()

		expect(OCP.AppConfig.setValue).toHaveBeenCalledTimes(1)
		const callbacks = vi.mocked(OCP.AppConfig.setValue).mock.calls[0][3]
		callbacks?.success?.()

		expect(wrapper.vm.lastSavedParallelWorkers).toBe('6')
		expect(wrapper.vm.saved).toBe(true)
	})
})
