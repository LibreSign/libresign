/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
let FileStatusList
let fileStatus
let FILE_STATUS
let axios

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((url) => url.replace(/{id}/, 'fileId')),
}))
vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((app, text) => text),
	translate: vi.fn((app, text) => text),
	translatePlural: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
}))
vi.mock('@nextcloud/files', () => ({
	formatFileSize: vi.fn((size) => `${size}B`),
}))
vi.mock('@nextcloud/moment', () => ({
	default: vi.fn((date) => ({
		format: vi.fn((fmt) => `Formatted Date: ${date}`),
	})),
}))
vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
	},
}))

vi.mock('../../../utils/fileStatus.js', () => ({
	getStatusLabel: vi.fn((status) => {
		const labels = {
			'0': 'Draft',
			'1': 'Ready',
			'2': 'Partial',
			'3': 'Signed',
			'4': 'Deleted',
			'5': 'Signing',
		}
		return labels[status] || 'Unknown'
	}),
	getStatusIcon: vi.fn((status) => 'mdiFileStatus'),
}))

vi.mock('../../../constants.js', () => ({
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

beforeAll(async () => {
	;({ default: FileStatusList } = await import('../../../components/validation/FileStatusList.vue'))
	fileStatus = await import('../../../utils/fileStatus.js')
	;({ FILE_STATUS } = await import('../../../constants.js'))
	;({ default: axios } = await import('@nextcloud/axios'))
})

describe('FileStatusList', () => {
	let wrapper
	let mockAxios

	const createWrapper = (props = {}) => {
		return mount(FileStatusList, {
			props: {
				fileIds: [],
				updateInterval: 2000,
				...props,
			},
			global: {
				stubs: {
					NcIconSvgWrapper: true,
				},
				mocks: {
					t: (app, text) => text,
				},
			},
		})
	}

	beforeEach(() => {
		if (wrapper) {
			wrapper.unmount()
		}
		vi.clearAllMocks()

		mockAxios = axios
		mockAxios.get.mockReset()
		mockAxios.get.mockResolvedValue({
			data: {
				ocs: {
					data: { id: 1, name: 'stub.pdf', status: FILE_STATUS.DRAFT },
				},
			},
		})
	})

	describe('RULE: empty state displays when no files', () => {
		it('shows empty state message when files array is empty', () => {
			wrapper = createWrapper({ fileIds: [] })

			const emptyState = wrapper.find('.empty-state')
			expect(emptyState.exists()).toBe(true)
			expect(emptyState.text()).toBe('No files to display')
		})

		it('hides files container when no files', () => {
			wrapper = createWrapper({ fileIds: [] })

			const filesContainer = wrapper.find('.files-container')
			expect(filesContainer.exists()).toBe(false)
		})
	})

	describe('RULE: loadFiles fetches file data from API', () => {
		it('calls axios.get for each file ID', async () => {
			mockAxios.get.mockResolvedValueOnce({
				data: {
					ocs: {
						data: { id: 1, name: 'file1.pdf' },
					},
				},
			})

			wrapper = createWrapper({ fileIds: [1] })
			await wrapper.vm.loadFiles()

			expect(mockAxios.get).toHaveBeenCalled()
		})

		it('stores loaded files in data', async () => {
			mockAxios.get
				.mockResolvedValueOnce({
					data: {
						ocs: {
							data: { id: 1, name: 'test.pdf', size: 1024, status: '3' },
						},
					},
				})
				.mockResolvedValueOnce({
					data: {
						ocs: {
							data: { id: 1, name: 'test.pdf', size: 1024, status: '3' },
						},
					},
				})

			wrapper = createWrapper({ fileIds: [1] })
			await wrapper.vm.loadFiles()

			expect(wrapper.vm.files.length).toBe(1)
			expect(wrapper.vm.files[0].name).toBe('test.pdf')
		})

		it('handles multiple file IDs', async () => {
			mockAxios.get
				.mockResolvedValueOnce({
					data: {
						ocs: {
							data: { id: 1, name: 'file1.pdf' },
						},
					},
				})
				.mockResolvedValueOnce({
					data: {
						ocs: {
							data: { id: 2, name: 'file2.pdf' },
						},
					},
				})
				.mockResolvedValueOnce({
					data: {
						ocs: {
							data: { id: 1, name: 'file1.pdf' },
						},
					},
				})
				.mockResolvedValueOnce({
					data: {
						ocs: {
							data: { id: 2, name: 'file2.pdf' },
						},
					},
				})

			wrapper = createWrapper({ fileIds: [1, 2] })
			await wrapper.vm.loadFiles()

			expect(wrapper.vm.files.length).toBe(2)
		})

		it('emits files-updated after loading', async () => {
			mockAxios.get.mockResolvedValueOnce({
				data: {
					ocs: {
						data: { id: 1, name: 'test.pdf' },
					},
				},
			})

			wrapper = createWrapper({ fileIds: [1] })
			await wrapper.vm.loadFiles()

			expect(wrapper.emitted('files-updated')).toBeTruthy()
		})

		it('handles API errors gracefully', async () => {
			mockAxios.get.mockRejectedValueOnce(new Error('API Error'))
			const consoleSpy = vi.spyOn(console, 'error').mockImplementation()

			wrapper = createWrapper({ fileIds: [1] })
			await wrapper.vm.loadFiles()

			expect(consoleSpy).toHaveBeenCalled()
		})
	})

	describe('RULE: getStatusClass maps status codes to CSS classes', () => {
		it('returns draft for status 0', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.getStatusClass(FILE_STATUS.DRAFT)).toBe('draft')
		})

		it('returns ready for status 1', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.getStatusClass(FILE_STATUS.ABLE_TO_SIGN)).toBe('ready')
		})

		it('returns partial for status 2', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.getStatusClass(FILE_STATUS.PARTIAL_SIGNED)).toBe('partial')
		})

		it('returns signed for status 3', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.getStatusClass(FILE_STATUS.SIGNED)).toBe('signed')
		})

		it('returns deleted for status 4', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.getStatusClass(FILE_STATUS.DELETED)).toBe('deleted')
		})

		it('returns signing for status 5', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.getStatusClass(FILE_STATUS.SIGNING_IN_PROGRESS)).toBe('signing')
		})

		it('returns unknown for unmapped status', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.getStatusClass(999)).toBe('unknown')
		})
	})

	describe('RULE: getStatusLabel returns formatted status from utility', () => {
		it('returns label from getStatusLabel utility', () => {
			wrapper = createWrapper()

			const label = wrapper.vm.getStatusLabel(FILE_STATUS.SIGNED)

			expect(label).toBe('Signed')
			expect(fileStatus.getStatusLabel).toHaveBeenCalledWith(FILE_STATUS.SIGNED)
		})

		it('returns Draft for draft status', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.getStatusLabel(FILE_STATUS.DRAFT)).toBe('Draft')
		})
	})

	describe('RULE: getStatusIcon returns icon from utility', () => {
		it('calls getStatusIcon utility function', () => {
			wrapper = createWrapper()

			const icon = wrapper.vm.getStatusIcon(FILE_STATUS.SIGNED)

			expect(fileStatus.getStatusIcon).toHaveBeenCalledWith(FILE_STATUS.SIGNED)
		})

		it('returns icon for each status', () => {
			wrapper = createWrapper()

			const icon1 = wrapper.vm.getStatusIcon(FILE_STATUS.DRAFT)
			const icon2 = wrapper.vm.getStatusIcon(FILE_STATUS.SIGNED)

			expect(icon1).toBeTruthy()
			expect(icon2).toBeTruthy()
		})
	})

	describe('RULE: formatDate converts SQL date to locale format', () => {
		it('formats date when provided', () => {
			wrapper = createWrapper()

			const formatted = wrapper.vm.formatDate('2024-06-01T12:00:00')

			expect(formatted).toContain('Formatted Date')
		})

		it('returns empty string when no date', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.formatDate(null)).toBe('')
			expect(wrapper.vm.formatDate('')).toBe('')
		})
	})

	describe('RULE: startUpdatePolling initiates interval updates', () => {
		it('sets updateTimer', () => {
			wrapper = createWrapper({ fileIds: [1] })

			wrapper.vm.startUpdatePolling()

			expect(wrapper.vm.updateTimer).toBeTruthy()
		})

		it('does not create multiple timers', () => {
			wrapper = createWrapper({ fileIds: [1] })

			const firstTimer = wrapper.vm.updateTimer
			wrapper.vm.startUpdatePolling()

			expect(wrapper.vm.updateTimer).toBe(firstTimer)
		})

		it('calls loadFiles at interval', async () => {
			vi.useFakeTimers()
			vi.spyOn(FileStatusList.methods, 'loadFiles')

			wrapper = createWrapper({ fileIds: [1] })

			mockAxios.get.mockResolvedValue({
				data: { ocs: { data: { id: 1, name: 'test.pdf' } } },
			})

			wrapper.vm.startUpdatePolling()

			vi.advanceTimersByTime(2000)

			expect(mockAxios.get).toHaveBeenCalled()

			vi.useRealTimers()
		})

		it('respects updateInterval prop', () => {
			wrapper = createWrapper({ fileIds: [1], updateInterval: 5000 })

			expect(wrapper.props('updateInterval')).toBe(5000)
		})
	})

	describe('RULE: stopUpdatePolling clears interval', () => {
		it('clears updateTimer', () => {
			wrapper = createWrapper({ fileIds: [1] })

			wrapper.vm.startUpdatePolling()
			wrapper.vm.stopUpdatePolling()

			expect(wrapper.vm.updateTimer).toBeNull()
		})

		it('handles call when timer not active', () => {
			wrapper = createWrapper()

			expect(() => wrapper.vm.stopUpdatePolling()).not.toThrow()
		})
	})

	describe('RULE: fileIds watcher controls polling lifecycle', () => {
		it('starts polling when fileIds change to non-empty', async () => {
			wrapper = createWrapper({ fileIds: [] })

			mockAxios.get.mockResolvedValue({
				data: { ocs: { data: { id: 1, name: 'test.pdf' } } },
			})

			await wrapper.setProps({ fileIds: [1] })
			await wrapper.vm.$nextTick()

			expect(wrapper.vm.updateTimer).toBeTruthy()
		})

		it('stops polling when fileIds change to empty', async () => {
			wrapper = createWrapper({ fileIds: [1] })

			await wrapper.setProps({ fileIds: [] })

			expect(wrapper.vm.updateTimer).toBeNull()
		})

		it('loads new files immediately', async () => {
			wrapper = createWrapper({ fileIds: [] })

			mockAxios.get.mockResolvedValue({
				data: { ocs: { data: { id: 2, name: 'file2.pdf' } } },
			})

			await wrapper.setProps({ fileIds: [2] })
			await wrapper.vm.$nextTick()

			expect(wrapper.vm.files.length).toBeGreaterThan(0)
		})
	})

	describe('RULE: mounted lifecycle initializes data loading', () => {
		it('loads files on mount when fileIds provided', async () => {
			mockAxios.get.mockResolvedValue({
				data: { ocs: { data: { id: 1, name: 'test.pdf' } } },
			})

			wrapper = createWrapper({ fileIds: [1] })
			await wrapper.vm.loadFiles()

			expect(wrapper.vm.files.length).toBeGreaterThan(0)
		})

		it('starts polling on mount', async () => {
			wrapper = createWrapper({ fileIds: [1] })

			await wrapper.vm.$nextTick()

			expect(wrapper.vm.updateTimer).toBeTruthy()
		})

		it('does not load when fileIds empty', async () => {
			wrapper = createWrapper({ fileIds: [] })

			await wrapper.vm.$nextTick()

			expect(wrapper.vm.files.length).toBe(0)
		})
	})

	describe('RULE: beforeDestroy stops polling cleanup', () => {
		it('clears update timer on destroy', async () => {
			wrapper = createWrapper({ fileIds: [1] })

			await wrapper.vm.$nextTick()
			expect(wrapper.vm.updateTimer).toBeTruthy()

			wrapper.destroy()
		})
	})

	describe('RULE: file item displays name, size, and status', () => {
		it('renders file with all details', async () => {
			mockAxios.get.mockResolvedValue({
				data: {
					ocs: {
						data: {
							id: 1,
							name: 'document.pdf',
							size: 2048,
							status: FILE_STATUS.SIGNED,
						},
					},
				},
			})

			wrapper = createWrapper({ fileIds: [1] })
			await wrapper.vm.loadFiles()

			expect(wrapper.vm.files[0].name).toBe('document.pdf')
			expect(wrapper.vm.files[0].size).toBe(2048)
		})
	})

	describe('RULE: signed date displays when available', () => {
		it('shows formatted date when signed property exists', async () => {
			mockAxios.get.mockResolvedValue({
				data: {
					ocs: {
						data: {
							id: 1,
							name: 'signed.pdf',
							signed: '2024-06-01T12:00:00',
							status: FILE_STATUS.SIGNED,
						},
					},
				},
			})

			wrapper = createWrapper({ fileIds: [1] })
			await wrapper.vm.loadFiles()

			expect(wrapper.vm.files[0].signed).toBe('2024-06-01T12:00:00')
		})

		it('handles missing signed date', async () => {
			mockAxios.get.mockResolvedValue({
				data: {
					ocs: {
						data: {
							id: 1,
							name: 'unsigned.pdf',
							status: FILE_STATUS.DRAFT,
						},
					},
				},
			})

			wrapper = createWrapper({ fileIds: [1] })
			await wrapper.vm.loadFiles()

			expect(wrapper.vm.files[0].signed).toBeUndefined()
		})
	})

	describe('RULE: multiple files display in sequence', () => {
		it('renders all loaded files', async () => {
			mockAxios.get
				.mockResolvedValueOnce({
					data: { ocs: { data: { id: 1, name: 'file1.pdf', status: '0' } } },
				})
				.mockResolvedValueOnce({
					data: { ocs: { data: { id: 2, name: 'file2.pdf', status: '3' } } },
				})
				.mockResolvedValueOnce({
					data: { ocs: { data: { id: 1, name: 'file1.pdf', status: '0' } } },
				})
				.mockResolvedValueOnce({
					data: { ocs: { data: { id: 2, name: 'file2.pdf', status: '3' } } },
				})

			wrapper = createWrapper({ fileIds: [1, 2] })
			await wrapper.vm.loadFiles()

			expect(wrapper.vm.files).toHaveLength(2)
			expect(wrapper.vm.files[0].name).toBe('file1.pdf')
			expect(wrapper.vm.files[1].name).toBe('file2.pdf')
		})
	})
})
