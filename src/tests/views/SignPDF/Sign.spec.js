/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { useSignMethodsStore } from '../../../store/signMethods.js'

// Global mock for axios - prevents unhandled rejections during component mounting
vi.mock('@nextcloud/axios', () => {
	const axiosMock = vi.fn().mockResolvedValue({
		data: {
			ocs: {
				data: {
					elements: []
				}
			}
		}
	})
	axiosMock.get = vi.fn().mockResolvedValue({
		data: {
			ocs: {
				data: {}
			}
		}
	})
	axiosMock.post = vi.fn().mockResolvedValue({
		data: {
			ocs: {
				data: {}
			}
		}
	})
	axiosMock.patch = vi.fn().mockResolvedValue({
		data: {
			ocs: {
				data: {}
			}
		}
	})
	axiosMock.delete = vi.fn().mockResolvedValue({
		data: {
			ocs: {
				data: {}
			}
		}
	})
	return {
		default: axiosMock,
	}
})

// Global mocks for other Nextcloud modules
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path) => `/ocs/v2.php/apps/libresign${path}`),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => null),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => ({})),
}))

describe('Sign.vue - signWithTokenCode', () => {
	let Sign
	let signMethodsStore
	let signStore
	let submitSignatureSpy

	beforeEach(async () => {
		setActivePinia(createPinia())

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

		it('successfully processes WhatsApp token signing', async () => {
			signMethodsStore.settings = {
				whatsappToken: { needCode: true },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('654321')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'whatsappToken',
				token: '654321',
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

	describe('proceedWithSigning - Full flow with WhatsApp token', () => {
		let proceedWithSigningLogic

		beforeEach(() => {
			setActivePinia(createPinia())
			// Function that simulates the proceedWithSigning logic
			proceedWithSigningLogic = (store, actionHandler) => {
				if (store.needClickToSign()) {
					actionHandler.showModal('clickToSign')
				} else if (store.needSignWithPassword()) {
					actionHandler.showModal('password')
				} else if (store.needTokenCode()) {
					actionHandler.showModal('token')
				}
			}
		})

		it('shows token modal when WhatsApp token is needed', () => {
			const store = useSignMethodsStore()
			store.settings = {
				whatsappToken: { needCode: true },
			}

			const actionHandler = {
				showModal: vi.fn(),
				closeModal: vi.fn(),
			}

			proceedWithSigningLogic(store, actionHandler)

			expect(actionHandler.showModal).toHaveBeenCalledWith('token')
		})

		it('shows password modal when password is needed (priority over token)', () => {
			const store = useSignMethodsStore()
			store.settings = {
				password: { hasSignatureFile: true },
				whatsappToken: { needCode: true },
			}

			const actionHandler = {
				showModal: vi.fn(),
				closeModal: vi.fn(),
			}

			proceedWithSigningLogic(store, actionHandler)

			expect(actionHandler.showModal).toHaveBeenCalledWith('password')
		})

		it('shows clickToSign modal when clickToSign is needed (highest priority)', () => {
			const store = useSignMethodsStore()
			store.settings = {
				clickToSign: {},
				password: { hasSignatureFile: true },
				whatsappToken: { needCode: true },
			}

			const actionHandler = {
				showModal: vi.fn(),
				closeModal: vi.fn(),
			}

			proceedWithSigningLogic(store, actionHandler)

			expect(actionHandler.showModal).toHaveBeenCalledWith('clickToSign')
		})

		it('does nothing when no signing method is configured', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			const actionHandler = {
				showModal: vi.fn(),
				closeModal: vi.fn(),
			}

			proceedWithSigningLogic(store, actionHandler)

			expect(actionHandler.showModal).not.toHaveBeenCalled()
		})
	})

	describe('Full signing flow with WhatsApp token', () => {
		let Sign
		let signMethodsStore
		let signStore
		let submitSignatureSpy

		beforeEach(async () => {
			setActivePinia(createPinia())

			// Import stores
			const { useSignStore } = await import('../../../store/sign.js')

			signMethodsStore = useSignMethodsStore()
			signStore = useSignStore()

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
					confirmSignDocument() {
						// Simulate the logic
						if (this.signMethodsStore.needTokenCode()) {
							this.signMethodsStore.showModal('token')
							return true
						}
						return false
					},
				},
			}

			submitSignatureSpy = vi.fn().mockResolvedValue({ status: 'signed', data: { id: 1 } })
		})

		it('complete flow: click sign button -> token modal opens -> submit token', async () => {
			signMethodsStore.settings = {
				whatsappToken: { needCode: true },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			// User clicks "Sign the document" button
			const result = instance.confirmSignDocument()
			expect(result).toBe(true)
			expect(signMethodsStore.modal.token).toBe(true)

			// User enters token and submits
			await instance.signWithTokenCode('123456')

			// Verify the submission happened
			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'whatsappToken',
				token: '123456',
			})
		})

		it('complete flow: click sign with multiple token methods enables first available', async () => {
			signMethodsStore.settings = {
				smsToken: { needCode: true },
				whatsappToken: { needCode: true },
				telegramToken: { needCode: true },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			// User clicks "Sign the document" button
			const result = instance.confirmSignDocument()
			expect(result).toBe(true)

			// User enters token - should use first method (SMS)
			await instance.signWithTokenCode('999999')

			// Verify the submission happened with SMS token
			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'smsToken',
				token: '999999',
			})
		})
	})

	describe('signWithTokenCode - Correct identify method from signature methods', () => {
		let Sign
		let signMethodsStore
		let signStore
		let submitSignatureSpy

		beforeEach(async () => {
			setActivePinia(createPinia())

			const { useSignStore } = await import('../../../store/sign.js')

			signMethodsStore = useSignMethodsStore()
			signStore = useSignStore()

			Sign = {
				data() {
					return {
						signMethodsStore,
						signStore,
						loading: false,
					}
				},
				methods: {
					// CORRECTED implementation - extracts identify method from signature method
					async signWithTokenCode(token) {
						const tokenMethods = ['smsToken', 'whatsappToken', 'signalToken', 'telegramToken', 'xmppToken']
						const activeMethod = tokenMethods.find(method =>
							Object.hasOwn(this.signMethodsStore.settings, method)
						)

						if (!activeMethod) {
							throw new Error('No active token method found')
						}

						// Extract the identify method from the signature method
						const signatureMethodData = this.signMethodsStore.settings[activeMethod]
						const identifyMethod = signatureMethodData.identifyMethod

						await this.submitSignature({
							method: identifyMethod,
							token,
						})
					},
					async submitSignature(payload) {
						return submitSignatureSpy(payload)
					},
				},
			}

			submitSignatureSpy = vi.fn().mockResolvedValue({ status: 'signed' })
		})

		it('FAILS: sends signature method name instead of identify method (whatsappToken vs whatsapp)', async () => {
			signMethodsStore.settings = {
				whatsappToken: {
					label: 'WhatsApp token',
					identifyMethod: 'whatsapp',
					needCode: true,
				},
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('123456')

			// This assertion FAILS with current implementation
			// because it sends 'whatsappToken' instead of 'whatsapp'
			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'whatsapp', // Should be the identify method
				token: '123456',
			})
		})

		it('FAILS: sends sms token name instead of identify method (smsToken vs sms)', async () => {
			signMethodsStore.settings = {
				smsToken: {
					label: 'SMS token',
					identifyMethod: 'sms',
					needCode: true,
				},
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('789012')

			// This assertion FAILS with current implementation
			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'sms', // Should be the identify method, not 'smsToken'
				token: '789012',
			})
		})

		it('FAILS: multiple token methods - uses wrong method name', async () => {
			signMethodsStore.settings = {
				smsToken: {
					label: 'SMS token',
					identifyMethod: 'sms',
					needCode: true,
				},
				whatsappToken: {
					label: 'WhatsApp token',
					identifyMethod: 'whatsapp',
					needCode: true,
				},
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('555555')

			// Should use 'sms' not 'smsToken'
			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'sms',
				token: '555555',
			})
		})

		it('FAILS: signal token - sends wrong method name', async () => {
			signMethodsStore.settings = {
				signalToken: {
					label: 'Signal token',
					identifyMethod: 'signal',
					needCode: true,
				},
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('444444')

			// Should send 'signal' not 'signalToken'
			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'signal',
				token: '444444',
			})
		})

		it('FAILS: backend expects identify method not signature method', async () => {
			signMethodsStore.settings = {
				whatsappToken: {
					label: 'WhatsApp token',
					identifyMethod: 'whatsapp',
					needCode: true,
					hashOfIdentifier: 'd41d8cd98f00b204e9800998ecf8427e',
				},
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('333333')

			// Backend endpoint expects 'whatsapp' (identify method)
			// not 'whatsappToken' (signature method)
			expect(submitSignatureSpy).not.toHaveBeenCalledWith({
				method: 'whatsappToken',
				token: '333333',
			})

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'whatsapp',
				token: '333333',
			})
		})
	})

	describe('signWithTokenCode - REAL component integration test', () => {
		it('INTEGRATION: extracts and sends correct identify method from signature methods data', async () => {
			const testCases = [
				{
					name: 'WhatsApp token',
					signatureMethodKey: 'whatsappToken',
					expectedIdentifyMethod: 'whatsapp',
					token: '123456',
				},
				{
					name: 'SMS token',
					signatureMethodKey: 'smsToken',
					expectedIdentifyMethod: 'sms',
					token: '789012',
				},
				{
					name: 'Signal token',
					signatureMethodKey: 'signalToken',
					expectedIdentifyMethod: 'signal',
					token: '456789',
				},
			]

			for (const testCase of testCases) {
				setActivePinia(createPinia())

				// Import REAL Sign component
				const SignComponent = await import('../../../views/SignPDF/_partials/Sign.vue')
				const realSign = SignComponent.default

				const signMethodsStore = useSignMethodsStore()

				// Set up signature method with identify method info
				signMethodsStore.settings = {
					[testCase.signatureMethodKey]: {
						label: testCase.name,
						identifyMethod: testCase.expectedIdentifyMethod,
						needCode: true,
					},
				}

				const submitSignatureMock = vi.fn().mockResolvedValue({ status: 'signed' })

				// Mount REAL component
				const wrapper = mount(realSign, {
					global: {
						stubs: {
							NcButton: true,
							NcDialog: true,
							NcLoadingIcon: true,
							TokenManager: true,
							EmailManager: true,
							UploadCertificate: true,
							Documents: true,
							Signatures: true,
							Draw: true,
							ManagePassword: true,
							CreatePassword: true,
							NcNoteCard: true,
							NcPasswordField: true,
							NcRichText: true,
						},
						mocks: {
							$emit: vi.fn(),
							$watch: vi.fn(),
							$nextTick: vi.fn(),
						},
					},
					props: {},
				})

				wrapper.vm.submitSignature = submitSignatureMock

				// Call real signWithTokenCode method
				await wrapper.vm.signWithTokenCode(testCase.token)

				// VERIFY: Must send identify method, NOT signature method name
				expect(submitSignatureMock).toHaveBeenCalledWith({
					method: testCase.expectedIdentifyMethod, // 'whatsapp', 'sms', 'signal'
					token: testCase.token,
				})

				// Double-check: Should NOT send the signature method key name
				expect(submitSignatureMock).not.toHaveBeenCalledWith({
					method: testCase.signatureMethodKey, // NOT 'whatsappToken', 'smsToken', etc
					token: testCase.token,
				})
			}
		})
	})

	describe('Sign.vue - envelope visible elements', () => {
		it('includes elements from child files when document has no signers', async () => {
			setActivePinia(createPinia())

			const SignComponent = await import('../../../views/SignPDF/_partials/Sign.vue')
			const realSign = SignComponent.default
			const { useSignStore } = await import('../../../store/sign.js')
			const { useSignatureElementsStore } = await import('../../../store/signatureElements.js')

			const signStore = useSignStore()
			const signatureElementsStore = useSignatureElementsStore()

			signStore.document = {
				id: 1,
				nodeType: 'envelope',
				signers: [],
				files: [
					{
						id: 10,
						signers: [
							{ signRequestId: 501, me: true },
						],
						visibleElements: [
							{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature' },
						],
					},
				],
			}

			signatureElementsStore.signs.signature = {
				id: 1,
				type: 'signature',
				file: { url: '/sig.png', nodeId: 11623 },
				starred: 0,
				createdAt: '2024-01-01',
			}

			const wrapper = mount(realSign, {
				global: {
					stubs: {
						NcButton: true,
						NcDialog: true,
						NcLoadingIcon: true,
						TokenManager: true,
						EmailManager: true,
						UploadCertificate: true,
						Documents: true,
						Signatures: true,
						Draw: true,
						ManagePassword: true,
						CreatePassword: true,
						NcNoteCard: true,
						NcPasswordField: true,
						NcRichText: true,
					},
					mocks: {
						$emit: vi.fn(),
						$watch: vi.fn(),
						$nextTick: vi.fn(),
					},
				},
			})

			expect(wrapper.vm.elements).toEqual([
				{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature' },
			])
		})
	})
})
