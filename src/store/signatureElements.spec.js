/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'

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

vi.mock('vue', () => ({
	set: vi.fn((obj, key, value) => {
		obj[key] = value
	}),
}))

describe('signatureElements store - regras de negócio de assinaturas', () => {
	let useSignatureElementsStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
		vi.resetModules()

		const module = await import('./signatureElements.js')
		useSignatureElementsStore = module.useSignatureElementsStore
	})

	describe('REGRA: hasSignatureOfType valida se usuário tem assinatura do tipo', () => {
		it('retorna true quando assinatura tem createdAt preenchido', () => {
			loadState.mockReturnValue([{
				type: 'signature',
				createdAt: '2024-01-01',
				file: { url: '/test.png', nodeId: 123 },
			}])

			const store = useSignatureElementsStore()

			expect(store.hasSignatureOfType('signature')).toBe(true)
		})

		it('retorna false quando assinatura não tem createdAt', () => {
			loadState.mockReturnValue([])

			const store = useSignatureElementsStore()

			expect(store.hasSignatureOfType('signature')).toBe(false)
		})
	})

	describe('REGRA: save deve usar PATCH se assinatura existe, POST se não existe', () => {
		it('usa PATCH quando assinatura já existe (nodeId > 0)', async () => {
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

		it('usa POST quando assinatura não existe (nodeId = 0)', async () => {
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

	describe('REGRA: salvar com signRequestUuid deve enviar header específico', () => {
		it('inclui header quando tem signRequestUuid', async () => {
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

		it('não inclui header quando não tem signRequestUuid', async () => {
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

	describe('REGRA: delete deve limpar assinatura e usar emptyElement', () => {
		it('delete deve resetar assinatura para estado vazio', async () => {
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

		it('delete com erro 404 deve resetar assinatura mesmo assim', async () => {
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

		it('delete deve incluir signRequestUuid no header quando disponível', async () => {
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

	describe('REGRA: loadSignatures só deve executar uma vez', () => {
		it('carrega assinaturas do loadState se disponível', () => {
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

		it('busca do servidor se loadState não tem assinaturas', async () => {
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

	describe('REGRA: tratamento de erros', () => {
		it('save deve capturar erro com campo errors', async () => {
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

		it('save deve capturar erro sem campo errors', async () => {
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
