/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Mock, MockedFunction } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'

type AxiosMock = Mock & {
	get: Mock
	post: Mock
	patch: Mock
	delete: Mock
}

// Mock @nextcloud/logger to avoid import-time errors
vi.mock('@nextcloud/logger', () => ({
	getLogger: vi.fn(() => ({
		error: vi.fn(),
		warn: vi.fn(),
		info: vi.fn(),
		debug: vi.fn(),
	})),
	getLoggerBuilder: vi.fn(() => ({
		setApp: vi.fn().mockReturnThis(),
		detectUser: vi.fn().mockReturnThis(),
		build: vi.fn(() => ({
			error: vi.fn(),
			warn: vi.fn(),
			info: vi.fn(),
			debug: vi.fn(),
		})),
	})),
}))

vi.mock('@nextcloud/axios', () => {
	const axiosInstanceMock = Object.assign(vi.fn(), {
		get: vi.fn(),
		post: vi.fn(),
		patch: vi.fn(),
		delete: vi.fn(),
	})
	return {
		default: axiosInstanceMock,
	}
})

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app: string, key: string, defaultValue: unknown) => defaultValue),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string, params?: Record<string, string>) => {
		let url = `/ocs/v2.php${path}`
		if (params) {
			Object.entries(params).forEach(([key, value]) => {
				url = url.replace(`{${key}}`, value)
			})
		}
		return url
	}),
}))

vi.mock('vue', async () => {
	const actual = await vi.importActual('vue')
	return {
		...actual,
		default: {
			...actual,
			set: vi.fn((obj: Record<string, unknown>, key: string, value: unknown) => {
				obj[key] = value
			}),
		},
	}
})

describe('signatureElements store - signature business rules', () => {
	const axiosMock = axios as unknown as AxiosMock
	const loadStateMock = loadState as MockedFunction<typeof loadState>
	let useSignatureElementsStore: typeof import('../../store/signatureElements.js').useSignatureElementsStore

	beforeAll(async () => {
		const module = await import('../../store/signatureElements.js')
		useSignatureElementsStore = module.useSignatureElementsStore
	})

	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	describe('RULE: hasSignatureOfType validates if user has signature of type', () => {
		it('returns true when signature has createdAt filled', () => {
			loadStateMock.mockReturnValue([{
				type: 'signature',
				createdAt: '2024-01-01',
				file: { url: '/test.png', nodeId: 123 },
			}])

			const store = useSignatureElementsStore()

			expect(store.hasSignatureOfType('signature')).toBe(true)
		})

		it('returns false when signature has no createdAt', () => {
			loadStateMock.mockReturnValue([])

			const store = useSignatureElementsStore()

			expect(store.hasSignatureOfType('signature')).toBe(false)
		})
	})

	describe('RULE: save should use PATCH if signature exists, POST if not', () => {
		it('uses PATCH when signature already exists (nodeId > 0)', async () => {
			loadStateMock.mockImplementation((app, key, defaultValue) => {
				if (key === 'user_signatures') {
					return [{
						type: 'signature',
						createdAt: '2024-01-01',
						file: { url: '/test.png', nodeId: 123 },
					}]
				}
				return defaultValue
			})

			axiosMock.mockResolvedValue({
				data: {
					ocs: {
						data: {
							message: 'Updated',
							elements: [],
						},
					},
				},
			})

			const store = useSignatureElementsStore()
			await store.save('signature', 'base64data')

			expect(axiosMock).toHaveBeenCalledWith(
				expect.objectContaining({
					method: 'patch',
					url: '/ocs/v2.php/apps/libresign/api/v1/signature/elements/123',
				})
			)
		})

		it('uses POST when signature does not exist (nodeId = 0)', async () => {
			loadStateMock.mockReturnValue([])

			axiosMock.mockResolvedValue({
				data: {
					ocs: {
						data: {
							message: 'Created',
							elements: [],
						},
					},
				},
			})

			const store = useSignatureElementsStore()
			expect(store.signs.signature.file.nodeId).toBe(0)

			await store.save('signature', 'base64data')

			expect(axiosMock).toHaveBeenCalledWith(
				expect.objectContaining({
					method: 'post',
					url: '/ocs/v2.php/apps/libresign/api/v1/signature/elements',
				})
			)
		})
	})

	describe('RULE: saving with signRequestUuid should send specific header', () => {
		it('includes header when has signRequestUuid', async () => {
			loadStateMock.mockImplementation((app, key, defaultValue) => {
				if (key === 'sign_request_uuid') return 'uuid-123'
				if (key === 'user_signatures') return []
				return defaultValue
			})

			axiosMock.mockResolvedValue({
				data: {
					ocs: {
						data: {
							message: 'Saved',
							elements: [],
						},
					},
				},
			})

			const store = useSignatureElementsStore()
			vi.clearAllMocks()

			await store.save('signature', 'base64data')

			expect(axiosMock).toHaveBeenCalledWith(
				expect.objectContaining({
					headers: {
						'libresign-sign-request-uuid': 'uuid-123',
					},
				})
			)
		})

		it('does not include header when has no signRequestUuid', async () => {
			loadStateMock.mockImplementation((app, key, defaultValue) => {
				if (key === 'sign_request_uuid') return ''
				if (key === 'user_signatures') return []
				return defaultValue
			})

			axiosMock.mockResolvedValue({
				data: {
					ocs: {
						data: {
							message: 'Saved',
							elements: [],
						},
					},
				},
			})

			const store = useSignatureElementsStore()
			vi.clearAllMocks()

			await store.save('signature', 'base64data')

			const call = axiosMock.mock.calls[0][0]
			expect(call.headers).toBeUndefined()
		})
	})

	describe('RULE: delete should clear signature and use emptyElement', () => {
		it('delete should reset signature to empty state', async () => {
			loadStateMock.mockImplementation((app, key, defaultValue) => {
				if (key === 'user_signatures') {
					return [{
						type: 'signature',
						createdAt: '2024-01-01',
						file: { url: '/file.png', nodeId: 123 },
					}]
				}
				return defaultValue
			})

			axiosMock.mockResolvedValue({
				data: {
					ocs: {
						data: {
							message: 'Deleted',
						},
					},
				},
			})

			const store = useSignatureElementsStore()
			expect(store.hasSignatureOfType('signature')).toBe(true)

			await store.delete('signature')

			expect(store.signs.signature.createdAt).toBe('')
			expect(store.signs.signature.file.nodeId).toBe(0)
		})

		it('delete with 404 error should reset signature anyway', async () => {
			loadStateMock.mockImplementation((app, key, defaultValue) => {
				if (key === 'user_signatures') {
					return [{
						type: 'signature',
						createdAt: '2024-01-01',
						file: { url: '/file.png', nodeId: 123 },
					}]
				}
				return defaultValue
			})

			axiosMock.mockRejectedValue({
				response: {
					status: 404,
				},
			})

			const store = useSignatureElementsStore()
			expect(store.hasSignatureOfType('signature')).toBe(true)

			await store.delete('signature')

			expect(store.signs.signature.createdAt).toBe('')
			expect(store.signs.signature.file.nodeId).toBe(0)
		})

		it('delete should include signRequestUuid in header when available', async () => {
			loadStateMock.mockImplementation((app, key, defaultValue) => {
				if (key === 'sign_request_uuid') return 'uuid-123'
				if (key === 'user_signatures') {
					return [{
						type: 'signature',
						createdAt: '2024-01-01',
						file: { url: '/file.png', nodeId: 123 },
					}]
				}
				return defaultValue
			})

			axiosMock.mockResolvedValue({
				data: {
					ocs: {
						data: {
							message: 'Deleted',
						},
					},
				},
			})

			const store = useSignatureElementsStore()
			vi.clearAllMocks()

			await store.delete('signature')

			expect(axiosMock).toHaveBeenCalledWith(
				expect.objectContaining({
					headers: {
						'libresign-sign-request-uuid': 'uuid-123',
					},
				})
			)
		})
	})

	describe('RULE: loadSignatures should only execute once', () => {
		it('loads signatures from loadState if available', () => {
			loadStateMock.mockImplementation((app, key, defaultValue) => {
				if (key === 'user_signatures') {
					return [
						{
							type: 'signature',
							createdAt: '2024-01-01',
							file: { url: '/file.png', nodeId: 123 },
						},
						{
							type: 'initial',
							createdAt: '2024-01-02',
							file: { url: '/initial.png', nodeId: 456 },
						},
					]
				}
				return defaultValue
			})

			const store = useSignatureElementsStore()

			expect(store.signs.signature.createdAt).toBe('2024-01-01')
			expect(store.signs.initial.createdAt).toBe('2024-01-02')
			expect(axiosMock).not.toHaveBeenCalled()
		})

		it('fetches from server if loadState has no signatures', async () => {
			loadStateMock.mockImplementation((app, key, defaultValue) => {
				return defaultValue
			})

			axiosMock.mockResolvedValue({
				data: {
					ocs: {
						data: {
							elements: [{
								type: 'signature',
								createdAt: '2024-01-01',
								file: { url: '/file.png', nodeId: 123 },
							}],
						},
					},
				},
			})

			const store = useSignatureElementsStore()

			expect(axiosMock).toHaveBeenCalledWith(
				expect.objectContaining({
					method: 'get',
					url: '/ocs/v2.php/apps/libresign/api/v1/signature/elements',
				})
			)
		})
	})

	describe('RULE: error handling', () => {
		it('save should capture error with errors field', async () => {
			loadStateMock.mockReturnValue([])

			axiosMock.mockRejectedValue({
				response: {
					data: {
						ocs: {
							data: {
								errors: [{ field: 'signature', message: 'Invalid format' }],
							},
						},
					},
				},
			})

			const store = useSignatureElementsStore()
			await store.save('signature', 'invalid')

			expect(store.error).toEqual({ field: 'signature', message: 'Invalid format' })
		})

		it('save should capture error without errors field', async () => {
			loadStateMock.mockReturnValue([])

			axiosMock.mockRejectedValue({
				response: {
					data: {
						ocs: {
							data: {
								message: 'Generic error',
							},
						},
					},
				},
			})

			const store = useSignatureElementsStore()
			await store.save('signature', 'invalid')

			expect(store.error).toEqual({ message: 'Generic error' })
		})
	})
})
