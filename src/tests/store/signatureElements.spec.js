/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'

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
	const axiosMock = vi.fn()
	axiosMock.get = vi.fn()
	axiosMock.post = vi.fn()
	axiosMock.patch = vi.fn()
	axiosMock.delete = vi.fn()
	return {
		default: axiosMock,
	}
})

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path, params) => {
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
			set: vi.fn((obj, key, value) => {
				obj[key] = value
			}),
		},
	}
})

describe('signatureElements store - signature business rules', () => {
	let useSignatureElementsStore

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
			loadState.mockReturnValue([{
				type: 'signature',
				createdAt: '2024-01-01',
				file: { url: '/test.png', nodeId: 123 },
			}])

			const store = useSignatureElementsStore()

			expect(store.hasSignatureOfType('signature')).toBe(true)
		})

		it('returns false when signature has no createdAt', () => {
			loadState.mockReturnValue([])

			const store = useSignatureElementsStore()

			expect(store.hasSignatureOfType('signature')).toBe(false)
		})
	})

	describe('RULE: save should use PATCH if signature exists, POST if not', () => {
		it('uses PATCH when signature already exists (nodeId > 0)', async () => {
			loadState.mockImplementation((app, key, defaultValue) => {
				if (key === 'user_signatures') {
					return [{
						type: 'signature',
						createdAt: '2024-01-01',
						file: { url: '/test.png', nodeId: 123 },
					}]
				}
				return defaultValue
			})

			axios.mockResolvedValue({
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

			expect(axios).toHaveBeenCalledWith(
				expect.objectContaining({
					method: 'patch',
					url: '/ocs/v2.php/apps/libresign/api/v1/signature/elements/123',
				})
			)
		})

		it('uses POST when signature does not exist (nodeId = 0)', async () => {
			loadState.mockReturnValue([])

			axios.mockResolvedValue({
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

			expect(axios).toHaveBeenCalledWith(
				expect.objectContaining({
					method: 'post',
					url: '/ocs/v2.php/apps/libresign/api/v1/signature/elements',
				})
			)
		})
	})

	describe('RULE: saving with signRequestUuid should send specific header', () => {
		it('includes header when has signRequestUuid', async () => {
			loadState.mockImplementation((app, key, defaultValue) => {
				if (key === 'sign_request_uuid') return 'uuid-123'
				if (key === 'user_signatures') return []
				return defaultValue
			})

			axios.mockResolvedValue({
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

			expect(axios).toHaveBeenCalledWith(
				expect.objectContaining({
					headers: {
						'libresign-sign-request-uuid': 'uuid-123',
					},
				})
			)
		})

		it('does not include header when has no signRequestUuid', async () => {
			loadState.mockImplementation((app, key, defaultValue) => {
				if (key === 'sign_request_uuid') return ''
				if (key === 'user_signatures') return []
				return defaultValue
			})

			axios.mockResolvedValue({
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

			const call = axios.mock.calls[0][0]
			expect(call.headers).toBeUndefined()
		})
	})

	describe('RULE: delete should clear signature and use emptyElement', () => {
		it('delete should reset signature to empty state', async () => {
			loadState.mockImplementation((app, key, defaultValue) => {
				if (key === 'user_signatures') {
					return [{
						type: 'signature',
						createdAt: '2024-01-01',
						file: { url: '/file.png', nodeId: 123 },
					}]
				}
				return defaultValue
			})

			axios.mockResolvedValue({
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
			loadState.mockImplementation((app, key, defaultValue) => {
				if (key === 'user_signatures') {
					return [{
						type: 'signature',
						createdAt: '2024-01-01',
						file: { url: '/file.png', nodeId: 123 },
					}]
				}
				return defaultValue
			})

			axios.mockRejectedValue({
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
			loadState.mockImplementation((app, key, defaultValue) => {
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

			axios.mockResolvedValue({
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

			expect(axios).toHaveBeenCalledWith(
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
			loadState.mockImplementation((app, key, defaultValue) => {
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
			expect(axios).not.toHaveBeenCalled()
		})

		it('fetches from server if loadState has no signatures', async () => {
			loadState.mockImplementation((app, key, defaultValue) => {
				return defaultValue
			})

			axios.mockResolvedValue({
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

			expect(axios).toHaveBeenCalledWith(
				expect.objectContaining({
					method: 'get',
					url: '/ocs/v2.php/apps/libresign/api/v1/signature/elements',
				})
			)
		})
	})

	describe('RULE: error handling', () => {
		it('save should capture error with errors field', async () => {
			loadState.mockReturnValue([])

			axios.mockRejectedValue({
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
			loadState.mockReturnValue([])

			axios.mockRejectedValue({
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
