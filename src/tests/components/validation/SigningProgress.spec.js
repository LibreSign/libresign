/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
let SigningProgress
let axios

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
	},
}))
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((url, params) => url),
}))
vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((app, text, vars) => {
		if (vars) {
			return text.replace(/{(\w+)}/g, (m, key) => vars[key])
		}
		return text
	}),
	translate: vi.fn((app, text, vars) => {
		if (vars) {
			return text.replace(/{(\w+)}/g, (m, key) => vars[key])
		}
		return text
	}),
	translatePlural: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
}))
vi.mock('../../../utils/fileStatus.js', () => ({
	buildStatusMap: vi.fn(() => ({
		'0': { label: 'Draft' },
		'1': { label: 'Ready to sign' },
		'3': { label: 'Signed' },
	})),
}))

beforeAll(async () => {
	;({ default: SigningProgress } = await import('../../../components/validation/SigningProgress.vue'))
	;({ default: axios } = await import('@nextcloud/axios'))
})

describe('SigningProgress', () => {
	let wrapper

	const createWrapper = (props = {}) => {
		return mount(SigningProgress, {
			propsData: {
				signRequestUuid: 'test-uuid-123',
				...props,
			},
			stubs: {
				NcLoadingIcon: true,
				NcNoteCard: true,
				NcIconSvgWrapper: true,
			},
			mocks: {
				t: (app, text, vars) => {
					if (vars) {
						return text.replace(/{(\w+)}/g, (m, key) => vars[key])
					}
					return text
				},
			},
		})
	}

	beforeEach(() => {
		if (wrapper) {
			wrapper.destroy()
		}
		vi.clearAllMocks()
		vi.useFakeTimers()
		axios.get.mockReset()
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	describe('RULE: getHeaderTitle returns appropriate title based on state', () => {
		it('returns polling title when isPolling true', () => {
			wrapper = createWrapper()
			wrapper.vm.isPolling = true

			expect(wrapper.vm.getHeaderTitle()).toBe('Signing document...')
		})

		it('returns error title when finished with errors', async () => {
			wrapper = createWrapper()
			wrapper.vm.isPolling = false
			wrapper.vm.progress = {
				signed: 1,
				inProgress: 0,
				pending: 1,
				total: 2,
				files: [
					{ id: 1, name: 'file1.pdf', error: { message: 'Error' } },
				],
			}
			await wrapper.vm.$nextTick()

			expect(wrapper.vm.getHeaderTitle()).toBe('Signing finished with errors')
		})
	})

	describe('RULE: getHeaderSubtitle returns status-appropriate message', () => {
		it('returns polling message when isPolling true', () => {
			wrapper = createWrapper()
			wrapper.vm.isPolling = true

			expect(wrapper.vm.getHeaderSubtitle()).toContain('being signed')
		})

		it('returns error message when finished with errors', async () => {
			wrapper = createWrapper()
			wrapper.vm.isPolling = false
			wrapper.vm.progress = {
				signed: 1,
				inProgress: 0,
				pending: 1,
				total: 2,
				files: [
					{ id: 1, name: 'file1.pdf', error: { message: 'Error' } },
				],
			}
			await wrapper.vm.$nextTick()

			expect(wrapper.vm.getHeaderSubtitle()).toContain('review the errors')
		})
	})

	describe('RULE: startPolling initiates polling when UUID provided', () => {
		it('starts polling when conditions met', () => {
			wrapper = createWrapper()
			wrapper.vm.stopPolling()
			const pollSpy = vi.spyOn(wrapper.vm, 'pollFileProgress').mockImplementation(() => {})

			wrapper.vm.startPolling()

			expect(wrapper.vm.isPolling).toBe(true)
			expect(pollSpy).toHaveBeenCalled()
		})

		it('does not start if already polling', () => {
			wrapper = createWrapper()
			wrapper.vm.isPolling = true
			wrapper.vm.pollFileProgress = vi.fn()

			wrapper.vm.startPolling()

			expect(wrapper.vm.pollFileProgress).not.toHaveBeenCalled()
		})

		it('does nothing without signRequestUuid', () => {
			wrapper = createWrapper({ signRequestUuid: '' })
			wrapper.vm.pollFileProgress = vi.fn()

			wrapper.vm.startPolling()

			expect(wrapper.vm.isPolling).toBe(false)
		})
	})

	describe('RULE: stopPolling clears polling state and timer', () => {
		it('stops polling and clears interval', () => {
			wrapper = createWrapper()
			wrapper.vm.isPolling = true
			wrapper.vm.pollingInterval = setTimeout(() => {}, 1000)

			wrapper.vm.stopPolling()

			expect(wrapper.vm.isPolling).toBe(false)
			expect(wrapper.vm.pollingInterval).toBeNull()
		})
	})

	describe('RULE: getProgressState calculates state metrics', () => {
		it('calculates allProcessed when all signed or errored', () => {
			wrapper = createWrapper()
			wrapper.vm.progress = {
				signed: 2,
				inProgress: 0,
				pending: 0,
				total: 2,
				files: [
					{ id: 1, error: null },
					{ id: 2, error: null },
				],
			}

			const state = wrapper.vm.getProgressState()

			expect(state.allProcessed).toBe(true)
		})

		it('calculates error count', () => {
			wrapper = createWrapper()
			wrapper.vm.progress = {
				signed: 1,
				inProgress: 0,
				pending: 0,
				total: 2,
				files: [
					{ id: 1, error: { message: 'Error 1' } },
					{ id: 2, error: { message: 'Error 2' } },
				],
			}

			const state = wrapper.vm.getProgressState()

			expect(state.errorCount).toBe(2)
		})

		it('collects file errors', () => {
			wrapper = createWrapper()
			wrapper.vm.progress = {
				files: [
					{ id: 1, name: 'file1.pdf', error: { message: 'Error 1' } },
					{ id: 2, name: 'file2.pdf', error: null },
				],
			}

			const state = wrapper.vm.getProgressState()

			expect(state.fileErrors).toHaveLength(1)
			expect(state.fileErrors[0].error.message).toBe('Error 1')
		})
	})

	describe('RULE: getFileStatusMeta returns icon and label', () => {
		it('returns icon and label for file', () => {
			wrapper = createWrapper()
			wrapper.vm.statusMap = {
				'3': { label: 'Signed', icon: 'check' },
			}

			const meta = wrapper.vm.getFileStatusMeta({ status: 3 })

			expect(meta.label).toBe('Signed')
			expect(meta.icon).toBeTruthy()
		})

		it('returns error icon when file has error', () => {
			wrapper = createWrapper()

			const meta = wrapper.vm.getFileStatusMeta({
				status: 0,
				error: { message: 'Error' },
			})

			expect(meta.icon).toBeTruthy()
		})
	})

	describe('RULE: pollFileProgress emits events based on response', () => {
		it('emits status-changed with response status', async () => {
			axios.get = vi.fn().mockResolvedValue({
				data: { ocs: { data: { status: 'SIGNING_IN_PROGRESS' } } },
			})
			wrapper = createWrapper()
			wrapper.vm.isPolling = true

			await wrapper.vm.pollFileProgress()

			expect(wrapper.emitted('status-changed')).toBeTruthy()
		})

		it('emits completed when file returned', async () => {
			axios.get = vi.fn().mockResolvedValue({
				data: { ocs: { data: { file: { id: 1, name: 'signed.pdf' } } } },
			})
			wrapper = createWrapper()
			wrapper.vm.isPolling = true

			await wrapper.vm.pollFileProgress()

			expect(wrapper.emitted('completed')).toBeTruthy()
			expect(wrapper.vm.isPolling).toBe(false)
		})

		it('stops polling on error response', async () => {
			axios.get = vi.fn().mockResolvedValue({
				data: { ocs: { data: { error: { message: 'Signing failed' } } } },
			})
			wrapper = createWrapper()
			wrapper.vm.isPolling = true

			await wrapper.vm.pollFileProgress()

			expect(wrapper.vm.isPolling).toBe(false)
			expect(wrapper.vm.generalErrorMessage).toBe('Signing failed')
			expect(wrapper.emitted('error')).toBeTruthy()
		})

		it('emits file-errors when files have errors', async () => {
			axios.get = vi.fn().mockResolvedValue({
				data: { ocs: { data: {} } },
			})
			wrapper = createWrapper()
			wrapper.vm.isPolling = true
			wrapper.vm.progress = {
				files: [
					{ id: 1, error: { message: 'Error' } },
				],
			}

			await wrapper.vm.pollFileProgress()

			expect(wrapper.emitted('file-errors')).toBeTruthy()
		})
	})

	describe('RULE: mounted starts polling if UUID provided', async () => {
		it.skip('starts polling on mount with UUID', async () => {
			wrapper = createWrapper()
			wrapper.vm.startPolling = vi.fn()

			if (wrapper.vm.$options.mounted) {
				wrapper.vm.$options.mounted[0]?.call(wrapper.vm)
			}

			expect(wrapper.vm.startPolling).toHaveBeenCalled()
		})

		it.skip('does nothing on mount without UUID', async () => {
			wrapper = createWrapper({ signRequestUuid: '' })
			wrapper.vm.startPolling = vi.fn()

			if (wrapper.vm.$options.mounted) {
				wrapper.vm.$options.mounted[0]?.call(wrapper.vm)
			}

			expect(wrapper.vm.startPolling).not.toHaveBeenCalled()
		})
	})

	describe('RULE: beforeDestroy stops polling', () => {
		it.skip('stops polling on destroy', () => {
			wrapper = createWrapper()
			wrapper.vm.stopPolling = vi.fn()

			if (wrapper.vm.$options.beforeDestroy) {
				wrapper.vm.$options.beforeDestroy[0]?.call(wrapper.vm)
			}

			expect(wrapper.vm.stopPolling).toHaveBeenCalled()
		})
	})

	describe('RULE: watch signRequestUuid starts polling on change', async () => {
		it('starts polling when UUID changes', () => {
			wrapper = createWrapper({ signRequestUuid: 'old-uuid' })
			wrapper.vm.startPolling = vi.fn()

			wrapper.vm.$options.watch.signRequestUuid.call(wrapper.vm, 'new-uuid', 'old-uuid')

			expect(wrapper.vm.startPolling).toHaveBeenCalled()
		})

		it('does not restart when UUID is same', () => {
			wrapper = createWrapper()
			wrapper.vm.startPolling = vi.fn()

			wrapper.vm.$options.watch.signRequestUuid.call(wrapper.vm, 'same-uuid', 'same-uuid')

			expect(wrapper.vm.startPolling).not.toHaveBeenCalled()
		})
	})
})
