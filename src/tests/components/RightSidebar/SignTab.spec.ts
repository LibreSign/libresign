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
import type { TranslationFunction } from '../../test-types'
import type { MockedFunction } from 'vitest'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn(),
}))

import { loadState } from '@nextcloud/initial-state'

describe('SignTab', () => {
	let wrapper: VueWrapper<unknown> | null
	let signStore: ReturnType<typeof useSignStore>
	let mockRouter: {
		push: MockedFunction<(location: unknown) => Promise<unknown>>
		currentRoute: { value: { path: string } }
	}
	let pinia: Pinia

	const createWrapper = async (routePath = '/', mockPush = true) => {
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
		})
	}

	beforeEach(() => {
		pinia = createPinia()
		setActivePinia(pinia)
		signStore = useSignStore()
		if (wrapper) {
			wrapper.unmount()
			wrapper = null
		}
		vi.clearAllMocks()
	})

	describe('RULE: signEnabled checks if document status allows signing', () => {
		it('returns true for ABLE_TO_SIGN status', async () => {
			signStore.document = { status: FILE_STATUS.ABLE_TO_SIGN }
			wrapper = await createWrapper()

			expect(wrapper.vm.signEnabled()).toBe(true)
		})

		it('returns true for PARTIAL_SIGNED status', async () => {
			signStore.document = { status: FILE_STATUS.PARTIAL_SIGNED }
			wrapper = await createWrapper()

			expect(wrapper.vm.signEnabled()).toBe(true)
		})

		it('returns false for other statuses', async () => {
			signStore.document = { status: FILE_STATUS.SIGNED }
			wrapper = await createWrapper()

			expect(wrapper.vm.signEnabled()).toBe(false)
		})

		it('returns false for DRAFT status', async () => {
			signStore.document = { status: FILE_STATUS.DRAFT }
			wrapper = await createWrapper()

			expect(wrapper.vm.signEnabled()).toBe(false)
		})
	})

	describe('RULE: getSignRequestUuid uses fallback chain to find UUID', () => {
		it('uses document signRequestUuid when available', async () => {
			signStore.document = { signRequestUuid: 'doc-uuid' }
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBe('doc-uuid')
		})

		it('falls back to sign_request_uuid', async () => {
			signStore.document = { sign_request_uuid: 'doc-snake-uuid' }
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBe('doc-snake-uuid')
		})

		it('falls back to signUuid', async () => {
			signStore.document = { signUuid: 'sign-uuid' }
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBe('sign-uuid')
		})

		it('falls back to sign_uuid', async () => {
			signStore.document = { sign_uuid: 'sign-snake-uuid' }
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBe('sign-snake-uuid')
		})

		it('uses signer sign_uuid when document has none', async () => {
			signStore.document = {
				signers: [{ sign_uuid: 'signer-uuid' }],
			}
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBe('signer-uuid')
		})

		it('prefers document UUID over signer UUID', async () => {
			signStore.document = {
				signRequestUuid: 'doc-uuid',
				signers: [{ sign_uuid: 'signer-uuid' }],
			}
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBe('doc-uuid')
		})

		it('uses me signer when available', async () => {
			signStore.document = {
				signers: [
					{ sign_uuid: 'other-uuid' },
					{ me: true, sign_uuid: 'my-uuid' },
				],
			}
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBe('my-uuid')
		})

		it('falls back to first signer when no me', async () => {
			signStore.document = {
				signers: [
					{ sign_uuid: 'first-uuid' },
					{ sign_uuid: 'second-uuid' },
				],
			}
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBe('first-uuid')
		})

		it('falls back to loadState when nothing else available', async () => {
			vi.mocked(loadState).mockReturnValue('state-uuid')
			signStore.document = {}
			wrapper = await createWrapper()

			expect(wrapper.vm.getSignRequestUuid()).toBe('state-uuid')
			expect(loadState).toHaveBeenCalledWith('libresign', 'sign_request_uuid', null)
		})

		it('returns null when all sources empty', async () => {
			vi.mocked(loadState).mockReturnValue(null)
			signStore.document = {}
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
				params: {
					uuid: 'test-uuid',
					isAfterSigned: true,
				},
			})
		})

		it('routes to ValidationFileExternal for public path', async () => {
			wrapper = await createWrapper('/p/sign/abc')
			mockRouter.push.mockClear()

			await wrapper.vm.onSigned({ signRequestUuid: 'test-uuid' })

			expect(mockRouter.push).toHaveBeenCalledWith({
				name: 'ValidationFileExternal',
				params: {
					uuid: 'test-uuid',
					isAfterSigned: true,
				},
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
				params: {
					uuid: 'test-uuid',
					isAfterSigned: false,
					isAsync: true,
				},
			})
		})

		it('routes to ValidationFileExternal with async flag for public', async () => {
			wrapper = await createWrapper('/p/sign/xyz')
			mockRouter.push.mockClear()

			await wrapper.vm.onSigningStarted({ signRequestUuid: 'test-uuid' })

			expect(mockRouter.push).toHaveBeenCalledWith({
				name: 'ValidationFileExternal',
				params: {
					uuid: 'test-uuid',
					isAfterSigned: false,
					isAsync: true,
				},
			})
		})
	})

	describe('RULE: mounted lifecycle triggers onSigningStarted for in-progress documents', () => {
		it('calls onSigningStarted when status is SIGNING_IN_PROGRESS', async () => {
			signStore.document = {
				status: FILE_STATUS.SIGNING_IN_PROGRESS,
				signRequestUuid: 'progress-uuid',
			}
			wrapper = await createWrapper('/', true)

			// onSigningStarted should be called in mounted hook and push to router
			expect(mockRouter.push).toHaveBeenCalledWith({
				name: 'ValidationFile',
				params: {
					uuid: 'progress-uuid',
					isAfterSigned: false,
					isAsync: true,
				},
			})
		})

		it('does not call onSigningStarted for other statuses', async () => {
			signStore.document = {
				status: FILE_STATUS.ABLE_TO_SIGN,
				signRequestUuid: 'able-uuid',
			}
			wrapper = await createWrapper('/', true)

			// Should not push for non-SIGNING_IN_PROGRESS status
			expect(mockRouter.push).not.toHaveBeenCalled()
		})

		it('does not call when UUID not available', async () => {
			signStore.document = {
				status: FILE_STATUS.SIGNING_IN_PROGRESS,
			}
			vi.mocked(loadState).mockReturnValue(null)
			wrapper = await createWrapper('/', true)

			// Should not push when UUID is not available
			expect(mockRouter.push).not.toHaveBeenCalled()
		})
	})
})
