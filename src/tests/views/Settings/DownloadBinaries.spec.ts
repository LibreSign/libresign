/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { interpolateL10n } from '../../testHelpers/l10n.js'
import { mount } from '@vue/test-utils'

import DownloadBinaries from '../../../views/Settings/DownloadBinaries.vue'

const generateOcsUrlMock = vi.fn((path: string) => path)
const useConfigureCheckStoreMock = vi.fn()
const listenMock = vi.fn()

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n({
	t: (_app: string, text: string, params?: Record<string, string>) => interpolateL10n(text, params),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (path: string) => generateOcsUrlMock(path),
}))

vi.mock('../../../store/configureCheck.js', () => ({
	useConfigureCheckStore: (...args: unknown[]) => useConfigureCheckStoreMock(...args),
}))

const EventSourceMock = vi.fn(function(this: { listen: (event: string, callback: (payload: string | unknown[]) => void) => void }) {
	this.listen = (event: string, callback: (payload: string | unknown[]) => void) => listenMock(event, callback)
})

const OC = {
	EventSource: EventSourceMock,
}

;(globalThis as typeof globalThis & { OC: typeof OC }).OC = OC

describe('DownloadBinaries.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		listenMock.mockClear()
		useConfigureCheckStoreMock.mockReturnValue({
			items: [],
			state: 'need download',
			downloadInProgress: false,
		})
	})

	function createWrapper() {
		return mount(DownloadBinaries, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcButton: { template: '<button><slot /><slot name="icon" /></button>' },
					NcNoteCard: { template: '<div><slot /></div>' },
					NcProgressBar: true,
					NcLoadingIcon: true,
				},
			},
		})
	}

	it('computes the download label from the configure check state', () => {
		const wrapper = createWrapper()

		expect(wrapper.vm.labelDownloadAllBinaries).toBe('Download binaries')
		expect(wrapper.vm.description).toContain('186MB')
	})

	it('subscribes to install progress events and updates state on completion', () => {
		const wrapper = createWrapper()
		const listeners = new Map<string, (payload: string | unknown[]) => void>()

		listenMock.mockImplementation((event: string, callback: (payload: string | unknown[]) => void) => {
			listeners.set(event, callback)
		})

		wrapper.vm.installAndValidate()

		expect(generateOcsUrlMock).toHaveBeenCalledWith('/apps/libresign/api/v1/admin/install-and-validate')
		expect(OC.EventSource).toHaveBeenCalledTimes(1)

		listeners.get('total_size')?.('{"java":25}')
		expect(wrapper.vm.configureCheckStore.state).toBe('downloading binaries')
		expect(wrapper.vm.downloadStatus.java).toBe(25)

		listeners.get('errors')?.('["network failed"]')
		expect(wrapper.vm.errors).toEqual(['network failed'])
		expect(wrapper.vm.configureCheckStore.state).toBe('need download')

		listeners.get('done')?.([])
		expect(wrapper.vm.configureCheckStore.state).toBe('done')
		expect(wrapper.vm.configureCheckStore.downloadInProgress).toBe(false)
		expect(Object.keys(wrapper.vm.downloadStatus)).toHaveLength(0)
	})
})
