/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import FileStatusList from '../../components/FileStatusList.vue'

const axiosGetMock = vi.fn()

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
}))

vi.mock('@nextcloud/files', () => ({
	formatFileSize: vi.fn((size: number) => `${size}B`),
}))

vi.mock('@nextcloud/moment', () => ({
	default: vi.fn((value: string) => ({
		calendar: vi.fn(() => `calendar:${value}`),
	})),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn((...args: unknown[]) => axiosGetMock(...args)),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => `/ocs/v2.php${path}`),
}))

vi.mock('../../utils/fileStatus.js', () => ({
	getStatusLabel: vi.fn((status: number) => `status:${status}`),
	getStatusIcon: vi.fn((status: number) => `icon:${status}`),
}))

vi.mock('../../constants.js', () => ({
	FILE_STATUS: {
		NOT_LIBRESIGN_FILE: 0,
		DRAFT: 0,
		ABLE_TO_SIGN: 1,
		PARTIAL_SIGNED: 2,
		SIGNED: 3,
		DELETED: 4,
		SIGNING_IN_PROGRESS: 5,
	},
}))

vi.mock('@nextcloud/vue/components/NcEmptyContent', () => ({
	default: {
		name: 'NcEmptyContent',
		props: ['name'],
		template: '<div class="nc-empty-content-stub">{{ name }}</div>',
	},
}))

vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({
	default: {
		name: 'NcIconSvgWrapper',
		template: '<i class="nc-icon-svg-wrapper-stub" />',
	},
}))

describe('FileStatusList.vue', () => {
	const createWrapper = (props: { fileIds?: number[]; updateInterval?: number } = {}) => mount(FileStatusList, {
		props: {
			fileIds: [],
			updateInterval: 2000,
			...props,
		},
	})

	beforeEach(() => {
		vi.useRealTimers()
		axiosGetMock.mockReset()
		axiosGetMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						data: [],
					},
				},
			},
		})
	})

	it('renders the empty state when there are no files to show', () => {
		const wrapper = createWrapper()

		expect(wrapper.find('.empty-state').exists()).toBe(true)
		expect(wrapper.find('.nc-empty-content-stub').text()).toBe('No files to sign')
	})

	it('loads the requested files and emits files-updated', async () => {
		axiosGetMock.mockResolvedValueOnce({
			data: {
				ocs: {
					data: {
						data: [
							{ id: 1, uuid: 'a', name: 'contract-a.pdf', size: 100, status: 1 },
							{ id: 2, uuid: 'b', name: 'contract-b.pdf', size: 250, status: 3 },
						],
					},
				},
			},
		})

		const wrapper = createWrapper({ fileIds: [2, 1] })
		await wrapper.vm.loadFiles()

		expect(axiosGetMock).toHaveBeenCalledWith('/ocs/v2.php/apps/libresign/api/v1/file/list', {
			params: { details: true },
			timeout: 10000,
		})
		expect(wrapper.vm.files.map((file: { id: number }) => file.id)).toEqual([2, 1])
		expect(wrapper.emitted('files-updated')?.at(-1)?.[0]).toEqual(wrapper.vm.files)
	})

	it('emits file-signed only for newly signed files', async () => {
		axiosGetMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						data: [
							{ id: 1, uuid: 'a', name: 'contract-a.pdf', size: 100, status: 3 },
						],
					},
				},
			},
		})

		const wrapper = createWrapper({ fileIds: [1] })
		await wrapper.vm.loadFiles()
		await wrapper.vm.loadFiles()

		expect(wrapper.emitted('file-signed')).toHaveLength(1)
		expect(wrapper.emitted('file-signed')?.[0]?.[0]).toMatchObject({ id: 1, status: 3 })
	})

	it('starts and stops polling with the configured interval', async () => {
		vi.useFakeTimers()
		axiosGetMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						data: [{ id: 1, uuid: 'a', name: 'contract-a.pdf', size: 100, status: 0 }],
					},
				},
			},
		})

		const wrapper = createWrapper({ fileIds: [1], updateInterval: 1500 })

		expect(wrapper.vm.updatePollingInterval).toBeTruthy()

		wrapper.vm.stopUpdatePolling()

		expect(wrapper.vm.updatePollingInterval).toBeNull()
	})

	it('renders the file size from the detailed payload', async () => {
		axiosGetMock.mockResolvedValueOnce({
			data: {
				ocs: {
					data: {
						data: [
							{ id: 1, uuid: 'a', name: 'contract-a.pdf', size: 2048, status: 1 },
						],
					},
				},
			},
		})

		const wrapper = createWrapper({ fileIds: [1] })
		await wrapper.vm.loadFiles()
		await wrapper.vm.$nextTick()

		expect(wrapper.find('.file-size').text()).toBe('2048B')
	})
})
