/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { Pinia } from 'pinia'
import SignTab from '../../../components/RightSidebar/SignTab.vue'
import { useSignStore } from '../../../store/sign.js'
import { useSidebarStore } from '../../../store/sidebar.js'
import { FILE_STATUS } from '../../../constants.js'
import type { SignatureMethodsRecord } from '../../../types/index'
import type { TranslationFunction } from '../../test-types'
import type { MockedFunction } from 'vitest'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn(),
}))

import { loadState } from '@nextcloud/initial-state'

type SignDocument = {
	id: number
	name: string
	description: string
	status: number
	statusText: string
	url: string
	nodeId: number
	nodeType: 'file' | 'envelope'
	uuid: string
	signers: Array<{
		me?: boolean
		status?: number
		sign_request_uuid?: string
		signatureMethods?: SignatureMethodsRecord
	}>
	visibleElements: Array<Record<string, unknown>>
	settings?: Record<string, unknown>
	[key: string]: unknown
}

type SignTabVm = {
	signEnabled: () => boolean
	getSignRequestUuid: () => string | null
	onSigned: (data: { signRequestUuid: string }) => Promise<void> | void
	onSigningStarted: (data: { signRequestUuid: string }) => Promise<void> | void
	showSidebar?: boolean
	activeTab?: string
	show?: boolean
	canShow?: boolean
	isVisible?: boolean
	setActiveTab?: (id?: string | null) => void
	hideSidebar?: () => void
	toggleSidebar?: () => void
	activeSignTab?: () => void
	activeRequestSignatureTab?: () => void
	handleRouteChange?: (routeName?: string) => void
	signStore?: ReturnType<typeof useSignStore>
	sidebarStore?: ReturnType<typeof useSidebarStore>
}

type SignTabWrapper = VueWrapper<SignTabVm>

const createDocument = (overrides: Partial<SignDocument> = {}): SignDocument => ({
	id: 1,
	name: 'Contract.pdf',
	description: 'Contract description',
	status: FILE_STATUS.DRAFT,
	statusText: 'Draft',
	url: '/apps/libresign/f/1',
	nodeId: 1,
	nodeType: 'file',
	uuid: 'document-uuid',
	signers: [],
	visibleElements: [],
	...overrides,
})

describe('SignTab', () => {
	let wrapper: SignTabWrapper | null
	let signStore: ReturnType<typeof useSignStore>
	let mockRouter: {
		push: MockedFunction<(location: unknown) => Promise<unknown>>
		currentRoute: { value: { path: string } }
	}
	let pinia: Pinia

	const createWrapper = async (routePath = '/', mockPush = true): Promise<SignTabWrapper> => {
		const t: TranslationFunction = (_app, text) => text
		// Create a mock router that the component will use via $router
		mockRouter = {
			push: vi.fn().mockResolvedValue(true),
			currentRoute: {
				value: {
					path: routePath,
				},
			},
		}

		return mount(SignTab, {
			global: {
				plugins: [pinia],
				mocks: {
					$router: mockRouter,
					$route: {
						path: routePath,
					},
					t,
				},
				stubs: {
					NcChip: true,
					Sign: true,
				},
			},
		}) as unknown as SignTabWrapper
	}

	beforeEach(() => {
		pinia = createPinia()
		setActivePinia(pinia)
		signStore = useSignStore()
		signStore.document = createDocument()
		if (wrapper) {
			wrapper.unmount()
			wrapper = null
		}
		vi.clearAllMocks()
	})

	describe('RULE: signEnabled checks if document status allows signing', () => {
		it('renders status from the document contract', async () => {
			signStore.document = createDocument({ statusText: 'Draft' })
			wrapper = await createWrapper()

			expect(wrapper.find('.document-status').text()).toContain('Draft')
		})

		it('returns true for ABLE_TO_SIGN status', async () => {
			signStore.document = createDocument({ status: FILE_STATUS.ABLE_TO_SIGN })
			wrapper = await createWrapper()

			expect(wrapper.vm.signEnabled()).toBe(true)
		})

		it('returns true for PARTIAL_SIGNED status', async () => {
			signStore.document = createDocument({ status: FILE_STATUS.PARTIAL_SIGNED })
			wrapper = await createWrapper()

			expect(wrapper.vm.signEnabled()).toBe(true)
		})

		it('returns false for other statuses', async () => {
			signStore.document = createDocument({ status: FILE_STATUS.SIGNED })
			wrapper = await createWrapper()

			expect(wrapper.vm.signEnabled()).toBe(false)
		})

		it('returns false for DRAFT status', async () => {
			signStore.document = createDocument({ status: FILE_STATUS.DRAFT })
			wrapper = await createWrapper()

			expect(wrapper.vm.signEnabled()).toBe(false)
		})
	})

	describe('RULE: getSignRequestUuid uses the current signer contract', () => {
		it('uses the current signer sign_request_uuid when available', async () => {
			signStore.document = createDocument({
				signers: [{ me: true, sign_request_uuid: 'signer-uuid' }],
			})
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBe('signer-uuid')
		})

		it('uses the file uuid for approver routes', async () => {
			signStore.document = createDocument({
				uuid: 'approver-file-uuid',
				settings: { isApprover: true },
				signers: [{ me: true, sign_request_uuid: 'signer-uuid' }],
			})
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBe('approver-file-uuid')
		})

		it('does not fall back to a non-current signer', async () => {
			signStore.document = createDocument({
				signers: [
					{ sign_request_uuid: 'first-uuid' },
					{ sign_request_uuid: 'second-uuid' },
				],
			})
			vi.mocked(loadState).mockReturnValue(null)
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBeNull()
		})

		it('falls back to loadState when no current signer uuid is available', async () => {
			vi.mocked(loadState).mockReturnValue('state-uuid')
			signStore.document = createDocument()
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBe('state-uuid')
			expect(loadState).toHaveBeenCalledWith('libresign', 'sign_request_uuid', null)
		})

		it('returns null when all sources empty', async () => {
			vi.mocked(loadState).mockReturnValue(null)
			signStore.document = createDocument()
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBeNull()
		})
	})

	describe('RULE: onSigned routes to validation page with isAfterSigned true', () => {
		it('routes to ValidationFile for internal path', async () => {
			wrapper = await createWrapper('/')
			mockRouter.push.mockClear()

			await wrapper.vm.onSigned({ signRequestUuid: 'test-uuid' })

			expect(mockRouter.push).toHaveBeenCalledWith({
				name: 'ValidationFile',
				params: { uuid: 'test-uuid' },
				state: { isAfterSigned: true },
			})
		})

		it('routes to ValidationFileExternal for public path', async () => {
			wrapper = await createWrapper('/p/sign/abc')
			mockRouter.push.mockClear()

			await wrapper.vm.onSigned({ signRequestUuid: 'test-uuid' })

			expect(mockRouter.push).toHaveBeenCalledWith({
				name: 'ValidationFileExternal',
				params: { uuid: 'test-uuid' },
				state: { isAfterSigned: true },
			})
		})
	})

	describe('RULE: onSigningStarted routes with isAfterSigned false and isAsync true', () => {
		it('routes to ValidationFile with async flag for internal', async () => {
			wrapper = await createWrapper('/')
			mockRouter.push.mockClear()

			await wrapper.vm.onSigningStarted({ signRequestUuid: 'test-uuid' })

			expect(mockRouter.push).toHaveBeenCalledWith({
				name: 'ValidationFile',
				params: { uuid: 'test-uuid' },
				state: { isAfterSigned: false, isAsync: true },
			})
		})

		it('routes to ValidationFileExternal with async flag for public', async () => {
			wrapper = await createWrapper('/p/sign/xyz')
			mockRouter.push.mockClear()

			await wrapper.vm.onSigningStarted({ signRequestUuid: 'test-uuid' })

			expect(mockRouter.push).toHaveBeenCalledWith({
				name: 'ValidationFileExternal',
				params: { uuid: 'test-uuid' },
				state: { isAfterSigned: false, isAsync: true },
			})
		})
	})

	describe('RULE: mounted lifecycle triggers onSigningStarted for in-progress documents', () => {
		it('calls onSigningStarted when status is SIGNING_IN_PROGRESS', async () => {
			signStore.document = createDocument({
				status: FILE_STATUS.SIGNING_IN_PROGRESS,
				signers: [{ me: true, sign_request_uuid: 'progress-uuid' }],
			})
			wrapper = await createWrapper('/', true)

			// onSigningStarted should be called in mounted hook and push to router
			expect(mockRouter.push).toHaveBeenCalledWith({
				name: 'ValidationFile',
				params: { uuid: 'progress-uuid' },
				state: { isAfterSigned: false, isAsync: true },
			})
		})

		it('does not call onSigningStarted for other statuses', async () => {
			signStore.document = createDocument({
				status: FILE_STATUS.ABLE_TO_SIGN,
				signers: [{ me: true, sign_request_uuid: 'able-uuid' }],
			})
			wrapper = await createWrapper('/', true)

			// Should not push for non-SIGNING_IN_PROGRESS status
			expect(mockRouter.push).not.toHaveBeenCalled()
		})

		it('does not call when UUID not available', async () => {
			signStore.document = createDocument({
				status: FILE_STATUS.SIGNING_IN_PROGRESS,
			})
			vi.mocked(loadState).mockReturnValue(null)
			wrapper = await createWrapper('/', true)

			// Should not push when UUID is not available
			expect(mockRouter.push).not.toHaveBeenCalled()
		})
	})
})
