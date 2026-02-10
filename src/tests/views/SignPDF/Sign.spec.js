/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

describe('Sign.vue - signWithTokenCode', () => {
	let Sign
	let signMethodsStore
	let signStore
	let submitSignatureSpy

	beforeEach(async () => {
		setActivePinia(createPinia())

		// Mock dependencies
		vi.mock('@nextcloud/vue', () => ({
			NcButton: { template: '<button />' },
			NcDialog: { template: '<div />' },
			NcLoadingIcon: { template: '<div />' },
		}))

		vi.mock('@nextcloud/axios', () => ({
			default: {
				get: vi.fn(),
			},
		}))

		vi.mock('@nextcloud/router', () => ({
			generateOcsUrl: vi.fn(),
		}))

		vi.mock('@nextcloud/auth', () => ({
			getCurrentUser: vi.fn(),
		}))

		// Import stores
		const { useSignMethodsStore } = await import('../../../store/signMethods.js')
		const { useSignStore } = await import('../../../store/sign.js')

		signMethodsStore = useSignMethodsStore()
		signStore = useSignStore()

		// Create a mock Sign component with the method we want to test
		Sign = {
			data() {
				return {
					signMethodsStore,
					signStore,
					loading: false,
				}
			},
			methods: {
				async signWithTokenCode(token) {
					const tokenMethods = ['smsToken', 'whatsappToken', 'signalToken', 'telegramToken', 'xmppToken']
					const activeMethod = tokenMethods.find(method =>
						Object.hasOwn(this.signMethodsStore.settings, method)
					)

					if (!activeMethod) {
						throw new Error('No active token method found')
					}

					await this.submitSignature({
						method: activeMethod,
						token,
					})
				},
				async submitSignature(payload) {
					// Spy on this method
					return submitSignatureSpy(payload)
				},
			},
		}

		submitSignatureSpy = vi.fn().mockResolvedValue({ status: 'signed' })
	})

	describe('signWithTokenCode', () => {
		it('detects SMS token method', async () => {
			signMethodsStore.settings = {
				smsToken: { needCode: true },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('123456')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'smsToken',
				token: '123456',
			})
		})

		it('detects WhatsApp token method', async () => {
			signMethodsStore.settings = {
				whatsappToken: { needCode: true },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('789012')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'whatsappToken',
				token: '789012',
			})
		})

		it('detects Signal token method', async () => {
			signMethodsStore.settings = {
				signalToken: { needCode: true },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('456789')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'signalToken',
				token: '456789',
			})
		})

		it('detects Telegram token method', async () => {
			signMethodsStore.settings = {
				telegramToken: { needCode: true },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('012345')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'telegramToken',
				token: '012345',
			})
		})

		it('detects XMPP token method', async () => {
			signMethodsStore.settings = {
				xmppToken: { needCode: true },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('678901')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'xmppToken',
				token: '678901',
			})
		})

		it('prefers first token method when multiple are present', async () => {
			signMethodsStore.settings = {
				smsToken: { needCode: true },
				whatsappToken: { needCode: true },
				signalToken: { needCode: true },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('111111')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'smsToken',
				token: '111111',
			})
		})

		it('throws error when no token method is found', async () => {
			signMethodsStore.settings = {
				clickToSign: {},
				password: {},
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await expect(instance.signWithTokenCode('123456')).rejects.toThrow('No active token method found')

			expect(submitSignatureSpy).not.toHaveBeenCalled()
		})

		it('throws error when settings is empty', async () => {
			signMethodsStore.settings = {}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await expect(instance.signWithTokenCode('123456')).rejects.toThrow('No active token method found')

			expect(submitSignatureSpy).not.toHaveBeenCalled()
		})

		it('passes token correctly to submitSignature', async () => {
			signMethodsStore.settings = {
				smsToken: { needCode: true },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			const testToken = 'abc123xyz'
			await instance.signWithTokenCode(testToken)

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'smsToken',
				token: testToken,
			})
		})

		it('ignores non-token methods in settings', async () => {
			signMethodsStore.settings = {
				clickToSign: {},
				emailToken: { needCode: true },
				password: { hasSignatureFile: true },
				smsToken: { needCode: true },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('123456')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'smsToken',
				token: '123456',
			})
		})
	})
})
