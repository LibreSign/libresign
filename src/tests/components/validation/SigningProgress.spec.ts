/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { interpolateL10n } from '../../testHelpers/l10n.js'
import type { MockedFunction } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import type { TranslationFunction } from '../../test-types'
import type { components } from '../../../types/openapi/openapi'

type SigningProgressComponent = typeof import('../../../components/validation/SigningProgress.vue').default
type StatusMeta = {
	label?: string
	icon?: string
	[key: string]: unknown
}

type ProgressFile = components['schemas']['ProgressFile']
type ProgressState = components['schemas']['ProgressPayload']

type SigningProgressVm = {
	progress: ProgressState | null
	statusMap: Record<string, StatusMeta>
	isPolling: boolean
	pollingInterval: ReturnType<typeof setTimeout> | null
	generalErrorMessage: string | null
	$nextTick: () => Promise<void>
	getHeaderTitle: () => string
	getHeaderSubtitle: () => string
	startPolling: () => void
	stopPolling: () => void
	getProgressState: () => { allProcessed: boolean; errorCount: number; fileErrors: Array<{ error: { message?: string } | null }> }
	getFileStatusMeta: (file: ProgressFile) => StatusMeta
	pollFileProgress: ReturnType<typeof vi.fn> | (() => Promise<void>)
	setProps?: (props: Record<string, unknown>) => Promise<void>
}

type SigningProgressWrapper = VueWrapper<any> & {
	vm: SigningProgressVm
	setProps: (props: Record<string, unknown>) => Promise<void>
}

type AxiosMock = {
	get: MockedFunction<(url: string) => Promise<{ data: { ocs: { data: unknown } } }>>
}

const translateMessage: TranslationFunction = (_app, text, vars) => {
	if (vars) {
		return text.replace(/{(\w+)}/g, (_m, key) => String(vars[key]))
	}
	return text
}

let SigningProgress: SigningProgressComponent
let axios: AxiosMock

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
	},
}))
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((url: string) => url),
}))
vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n({
	t: (_app: string, text: string, vars?: Record<string, unknown>) => interpolateL10n(text, vars),
}))
vi.mock('../../../utils/fileStatus.js', () => ({
	buildStatusMap: vi.fn(() => ({
		'0': { label: 'Draft' },
		'1': { label: 'Ready to sign' },
		'3': { label: 'Signed' },
		'5': { label: 'Signing' },
	})),
}))

beforeAll(async () => {
	;({ default: SigningProgress } = await import('../../../components/validation/SigningProgress.vue'))
	const axiosModule = await import('@nextcloud/axios')
	axios = axiosModule.default as unknown as AxiosMock
})

describe('SigningProgress', () => {
	let wrapper: SigningProgressWrapper | null

	const createWrapper = (props = {}): SigningProgressWrapper => {
		return mount(SigningProgress, {
			props: {
				signRequestUuid: 'test-uuid-123',
				...props,
			},
			stubs: {
				NcLoadingIcon: true,
				NcNoteCard: true,
				NcIconSvgWrapper: true,
			},
			mocks: {
				t: translateMessage,
			},
		}) as SigningProgressWrapper
	}

	beforeEach(() => {
		if (wrapper) {
			wrapper.unmount()
			wrapper = null
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
					{ id: 1, name: 'file1.pdf', status: 0, statusText: 'Draft', error: { message: 'Error' } },
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
					{ id: 1, name: 'file1.pdf', status: 0, statusText: 'Draft', error: { message: 'Error' } },
				],
			}
			await wrapper.vm.$nextTick()

			expect(wrapper.vm.getHeaderSubtitle()).toContain('review the errors')
		})
	})

	describe('RULE: startPolling initiates polling when UUID provided', () => {
		it('starts polling when conditions met', async () => {
			axios.get = vi.fn().mockResolvedValue({
				data: { ocs: { data: { status: 'SIGNING_IN_PROGRESS' } } },
			})
			wrapper = createWrapper()
			wrapper.vm.stopPolling()
			vi.clearAllMocks()

			wrapper.vm.startPolling()
			await wrapper.vm.$nextTick()

			expect(wrapper.vm.isPolling).toBe(true)
			expect(axios.get).toHaveBeenCalled()
		})

		it('does not start if already polling', () => {
			axios.get = vi.fn().mockResolvedValue({
				data: { ocs: { data: { status: 'SIGNING_IN_PROGRESS' } } },
			})
			wrapper = createWrapper()
			wrapper.vm.isPolling = true
			vi.clearAllMocks()

			wrapper.vm.startPolling()

			expect(axios.get).not.toHaveBeenCalled()
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
					{ id: 1, name: 'file1.pdf', status: 3, statusText: 'Signed', error: undefined },
					{ id: 2, name: 'file2.pdf', status: 3, statusText: 'Signed', error: undefined },
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
					{ id: 1, name: 'file1.pdf', status: 0, statusText: 'Draft', error: { message: 'Error 1' } },
					{ id: 2, name: 'file2.pdf', status: 0, statusText: 'Draft', error: { message: 'Error 2' } },
				],
			}

			const state = wrapper.vm.getProgressState()

			expect(state.errorCount).toBe(2)
		})

		it('collects file errors', () => {
			wrapper = createWrapper()
			wrapper.vm.progress = {
				total: 2,
				signed: 0,
				inProgress: 0,
				pending: 2,
				files: [
					{ id: 1, name: 'file1.pdf', status: 0, statusText: 'Draft', error: { message: 'Error 1' } },
					{ id: 2, name: 'file2.pdf', status: 0, statusText: 'Draft', error: undefined },
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

			const meta = wrapper.vm.getFileStatusMeta({ id: 1, name: 'file.pdf', status: 3, statusText: 'Signed' })

			expect(meta.label).toBe('Signed')
			expect(meta.icon).toBeTruthy()
		})

		it('returns error icon when file has error', () => {
			wrapper = createWrapper()

			const meta = wrapper.vm.getFileStatusMeta({
				id: 1,
				name: 'file.pdf',
				status: 0,
				statusText: 'Draft',
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
				total: 1,
				signed: 0,
				inProgress: 0,
				pending: 1,
				files: [
					{ id: 1, name: 'file.pdf', status: 0, statusText: 'Draft', error: { message: 'Error' } },
				],
			}

			await wrapper.vm.pollFileProgress()

			expect(wrapper.emitted('file-errors')).toBeTruthy()
		})
	})

	describe('RULE: mounted starts polling if UUID provided', async () => {
		it('starts polling on mount with UUID', async () => {
			axios.get = vi.fn().mockResolvedValue({
				data: { ocs: { data: { status: 'SIGNING_IN_PROGRESS' } } },
			})
			wrapper = createWrapper()
			await wrapper.vm.$nextTick()

			expect(wrapper.vm.isPolling).toBe(true)
			expect(axios.get).toHaveBeenCalled()
		})

		it('does nothing on mount without UUID', async () => {
			axios.get = vi.fn().mockResolvedValue({
				data: { ocs: { data: { status: 'SIGNING_IN_PROGRESS' } } },
			})
			wrapper = createWrapper({ signRequestUuid: '' })
			await wrapper.vm.$nextTick()

			expect(wrapper.vm.isPolling).toBe(false)
			expect(axios.get).not.toHaveBeenCalled()
		})
	})

	describe('RULE: beforeUnmount stops polling', () => {
		it('stops polling on unmount', () => {
			wrapper = createWrapper()
			wrapper.vm.isPolling = true
			wrapper.vm.pollingInterval = setTimeout(() => {}, 1000)

			wrapper.unmount()

			expect(wrapper.vm.isPolling).toBe(false)
			expect(wrapper.vm.pollingInterval).toBeNull()
		})
	})

	describe('RULE: watch signRequestUuid starts polling on change', async () => {
		it('starts polling when UUID changes', async () => {
			axios.get = vi.fn().mockResolvedValue({
				data: { ocs: { data: { status: 'SIGNING_IN_PROGRESS' } } },
			})
			wrapper = createWrapper({ signRequestUuid: 'old-uuid' })
			wrapper.vm.stopPolling()
			vi.clearAllMocks()

			await wrapper.setProps({ signRequestUuid: 'new-uuid' })

			expect(axios.get).toHaveBeenCalledWith(
				'/apps/libresign/api/v1/file/progress/new-uuid',
				expect.any(Object)
			)
		})

		it('does not restart when UUID is same', async () => {
			wrapper = createWrapper()
			wrapper.vm.stopPolling()

			await wrapper.setProps({ signRequestUuid: 'test-uuid-123' })

			expect(wrapper.vm.isPolling).toBe(false)
		})
	})

	describe('RULE: buildProgressFromValidation returns OpenAPI-compatible progress payloads', () => {
		it('builds file progress with required status text for envelope validation data', () => {
			wrapper = createWrapper()

			const progress = wrapper.vm.buildProgressFromValidation({
				nodeType: 'envelope',
				files: [
					{ id: 1, name: 'contract.pdf', status: 5 },
					{ id: 2, name: 'signed.pdf', status: 3 },
				],
			})

			expect(progress).toEqual({
				total: 2,
				signed: 1,
				inProgress: 1,
				pending: 0,
				files: [
					{ id: 1, name: 'contract.pdf', status: 5, statusText: 'Signing' },
					{ id: 2, name: 'signed.pdf', status: 3, statusText: 'Signed' },
				],
			})
		})

		it('builds signer progress with explicit inProgress field', () => {
			wrapper = createWrapper()

			const progress = wrapper.vm.buildProgressFromValidation({
				signers: [
					{ signed: true },
					{ signed: false },
				],
			})

			expect(progress).toEqual({
				total: 2,
				signed: 1,
				inProgress: 0,
				pending: 1,
			})
		})
	})
})
