/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { MockedFunction } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { flushPromises, mount } from '@vue/test-utils'
import { useSignMethodsStore } from '../../../store/signMethods.js'
import type { useSignStore } from '../../../store/sign.js'
import { FILE_STATUS, SIGN_REQUEST_STATUS } from '../../../constants.js'

type TokenMethodKey = 'smsToken' | 'whatsappToken' | 'signalToken' | 'telegramToken' | 'xmppToken'

type SignMethodSettings = {
	needCode?: boolean
	identifyMethod?: string
	label?: string
	hashOfIdentifier?: string
	blurredEmail?: string
	hasConfirmCode?: boolean
	token?: string
	hasSignatureFile?: boolean
}

type SignMethodsSettings = Partial<Record<TokenMethodKey, SignMethodSettings>> & {
	emailToken?: SignMethodSettings
	password?: SignMethodSettings
	clickToSign?: SignMethodSettings
}

type SignMethodsStore = ReturnType<typeof useSignMethodsStore> & {
	settings: SignMethodsSettings
}

type SignStore = ReturnType<typeof useSignStore>

type EnvelopeSigner = {
	signRequestId?: number
	me?: boolean
	[key: string]: unknown
}

type EnvelopeFile = {
	id?: number
	name?: string
	signers?: EnvelopeSigner[]
	visibleElements?: Array<Record<string, unknown>>
	[key: string]: unknown
}

type SignDocument = Omit<NonNullable<SignStore['document']>, 'signers' | 'files'> & {
	signers?: EnvelopeSigner[]
	files?: EnvelopeFile[]
}

type SubmitSignaturePayload = {
	method: string
	token: string
}

type SignComponent = {
	data: () => {
		signMethodsStore: SignMethodsStore
		signStore: SignStore
		loading: boolean
	}
	methods: {
		signWithTokenCode: (token: string) => Promise<void>
		submitSignature: (payload: SubmitSignaturePayload) => Promise<unknown>
	}
}

type SignComponentWithConfirm = SignComponent & {
	methods: SignComponent['methods'] & {
		confirmSignDocument: () => boolean
	}
}

type ActionHandler = {
	showModal: (modalCode: string) => void
	closeModal: (modalCode: string) => void
}

type ProceedWithSigningLogic = (store: SignMethodsStore, actionHandler: ActionHandler) => void

type SubmitSignatureCompatMethod = (this: Record<string, any>, payload?: {
	method?: string
	modalCode?: string
	token?: string
}) => Promise<void>

const createSignDocument = (overrides: Partial<SignDocument> = {}): SignDocument => ({
	id: 1,
	name: 'Test file',
	description: '',
	status: '',
	statusText: '',
	url: '/apps/libresign/p/pdf/test-file',
	nodeId: 1,
	nodeType: 'file',
	uuid: 'test-file-uuid',
	signers: [],
	visibleElements: [],
	...overrides,
})

// Global mock for axios - prevents unhandled rejections during component mounting
vi.mock('@nextcloud/axios', () => {
	const axiosInstanceMock = Object.assign(vi.fn().mockResolvedValue({
		data: {
			ocs: {
				data: {
					elements: [],
				},
			},
		},
	}), {
		get: vi.fn().mockResolvedValue({
			data: {
				ocs: {
					data: {},
				},
			},
		}),
		post: vi.fn().mockResolvedValue({
			data: {
				ocs: {
					data: {},
				},
			},
		}),
		patch: vi.fn().mockResolvedValue({
			data: {
				ocs: {
					data: {},
				},
			},
		}),
		delete: vi.fn().mockResolvedValue({
			data: {
				ocs: {
					data: {},
				},
			},
		}),
	})
	return {
		default: axiosInstanceMock,
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
	getCapabilities: vi.fn(() => ({
		libresign: {
			config: {
				'sign-elements': {
					'can-create-signature': true,
				},
			},
		},
	})),
}))

vi.mock('vue-select', () => ({
	default: {
		name: 'VSelect',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		render: () => null,
	},
}))

describe('Sign.vue - signWithTokenCode', () => {
	let Sign: SignComponent
	let signMethodsStore: SignMethodsStore
	let signStore: SignStore
	let submitSignatureSpy: MockedFunction<(payload: SubmitSignaturePayload) => Promise<unknown>>
	let submitSignatureCompatMethod: SubmitSignatureCompatMethod

	beforeAll(async () => {
		const SignComponent = await import('../../../views/SignPDF/_partials/Sign.vue')
		submitSignatureCompatMethod = (SignComponent.default as any).methods.submitSignature
	})

	beforeEach(async () => {
		setActivePinia(createPinia())

		// Import stores
		const { useSignMethodsStore } = await import('../../../store/signMethods.js')
		const { useSignStore } = await import('../../../store/sign.js')

		signMethodsStore = useSignMethodsStore() as SignMethodsStore
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
				async signWithTokenCode(
					this: {
						signMethodsStore: SignMethodsStore
						submitSignature: (payload: SubmitSignaturePayload) => Promise<unknown>
					},
					token: string,
				) {
					const tokenMethods: TokenMethodKey[] = ['smsToken', 'whatsappToken', 'signalToken', 'telegramToken', 'xmppToken']
					const activeMethod = tokenMethods.find(method =>
						Object.hasOwn(this.signMethodsStore.settings, method)
					)

					if (!activeMethod) {
						throw new Error('No active token method found')
					}

					const identifyMethod = this.signMethodsStore.settings[activeMethod]?.identifyMethod
					if (!identifyMethod) {
						throw new Error('No identify method found for active token method')
					}

					await this.submitSignature({
						method: identifyMethod,
						token,
					})
				},
				async submitSignature(payload: SubmitSignaturePayload) {
					// Spy on this method
					return submitSignatureSpy(payload)
				},
			},
		}

		submitSignatureSpy = vi.fn<(payload: SubmitSignaturePayload) => Promise<unknown>>()
			.mockResolvedValue({ status: 'signed' })
	})

	describe('signWithTokenCode', () => {
		it('detects SMS token method', async () => {
			signMethodsStore.settings = {
				smsToken: { needCode: true, identifyMethod: 'sms' },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('123456')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'sms',
				token: '123456',
			})
		})

		it('detects WhatsApp token method', async () => {
			signMethodsStore.settings = {
				whatsappToken: { needCode: true, identifyMethod: 'whatsapp' },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('789012')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'whatsapp',
				token: '789012',
			})
		})

		it('successfully processes WhatsApp token signing', async () => {
			signMethodsStore.settings = {
				whatsappToken: { needCode: true, identifyMethod: 'whatsapp' },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('654321')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'whatsapp',
				token: '654321',
			})
		})

		it('detects Signal token method', async () => {
			signMethodsStore.settings = {
				signalToken: { needCode: true, identifyMethod: 'signal' },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('456789')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'signal',
				token: '456789',
			})
		})

		it('detects Telegram token method', async () => {
			signMethodsStore.settings = {
				telegramToken: { needCode: true, identifyMethod: 'telegram' },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('012345')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'telegram',
				token: '012345',
			})
		})

		it('detects XMPP token method', async () => {
			signMethodsStore.settings = {
				xmppToken: { needCode: true, identifyMethod: 'xmpp' },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('678901')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'xmpp',
				token: '678901',
			})
		})

		it('prefers first token method when multiple are present', async () => {
			signMethodsStore.settings = {
				smsToken: { needCode: true, identifyMethod: 'sms' },
				whatsappToken: { needCode: true, identifyMethod: 'whatsapp' },
				signalToken: { needCode: true, identifyMethod: 'signal' },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('111111')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'sms',
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
				smsToken: { needCode: true, identifyMethod: 'sms' },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			const testToken = 'abc123xyz'
			await instance.signWithTokenCode(testToken)

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'sms',
				token: testToken,
			})
		})

		it('ignores non-token methods in settings', async () => {
			signMethodsStore.settings = {
				clickToSign: {},
				emailToken: { needCode: true },
				password: { hasSignatureFile: true },
				smsToken: { needCode: true, identifyMethod: 'sms' },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await instance.signWithTokenCode('123456')

			expect(submitSignatureSpy).toHaveBeenCalledWith({
				method: 'sms',
				token: '123456',
			})
		})

		it('throws error when active token method has no identify method', async () => {
			signMethodsStore.settings = {
				smsToken: { needCode: true },
			}

			const instance = {
				...Sign.data(),
				...Sign.methods,
			}

			await expect(instance.signWithTokenCode('123456')).rejects.toThrow('No identify method found for active token method')

			expect(submitSignatureSpy).not.toHaveBeenCalled()
		})
	})

	describe('Sign.vue - API error handling', () => {
			it('emits signed when envelope submit has mixed results and the final result is signed', async () => {
				const context = {
					loading: false,
					elements: [
						{ elementId: 101, signRequestId: 501, type: 'signature' },
						{ elementId: 102, signRequestId: 502, type: 'signature' },
					],
					canCreateSignature: false,
					signRequestUuid: 'fallback-uuid',
					signatureElementsStore: {
						signs: {},
					},
					actionHandler: {
						showModal: vi.fn(),
						closeModal: vi.fn(),
					},
					signMethodsStore: {
						certificateEngine: 'openssl',
					},
					signStore: {
						document: {
							id: 10,
							nodeType: 'envelope',
							signers: [
								{ me: true, signRequestId: 501, sign_request_uuid: 'uuid-file-a' },
								{ me: true, signRequestId: 502, sign_request_uuid: 'uuid-file-b' },
							],
						},
						clearSigningErrors: vi.fn(),
						setSigningErrors: vi.fn(),
						submitSignature: vi.fn()
							.mockResolvedValueOnce({
								status: 'signingInProgress',
								data: {},
							})
							.mockResolvedValueOnce({
								status: 'signed',
								data: {
									action: 3500,
								},
							}),
					},
					$emit: vi.fn(),
					sidebarStore: {
						hideSidebar: vi.fn(),
					},
				}

				await submitSignatureCompatMethod.call(context, {
					method: 'password',
					token: '123456',
				})

				expect(context.signStore.submitSignature).toHaveBeenCalledTimes(2)
				expect(context.$emit).toHaveBeenCalledWith('signed', expect.objectContaining({
					action: 3500,
					signRequestUuid: 'uuid-file-a',
				}))
				expect(context.$emit).not.toHaveBeenCalledWith('signing-started', expect.anything())
				expect(context.loading).toBe(false)
			})

		it('keeps certificate validation errors in signStore and does not open certificate modal', async () => {
			const apiErrors = [{ message: 'Certificate has been revoked', code: 422 }]
			const context = {
				loading: false,
				elements: [],
				canCreateSignature: false,
				signRequestUuid: 'test-sign-request-uuid',
				signMethodsStore: {
					certificateEngine: 'openssl',
				},
				signatureElementsStore: {
					signs: {},
				},
				actionHandler: {
					showModal: vi.fn(),
					closeModal: vi.fn(),
				},
				signStore: {
					document: { id: 10 },
					clearSigningErrors: vi.fn(),
					setSigningErrors: vi.fn(),
					submitSignature: vi.fn().mockRejectedValue({
						type: 'signError',
						errors: apiErrors,
					}),
				},
				$emit: vi.fn(),
				sidebarStore: {
					hideSidebar: vi.fn(),
				},
			}

			await submitSignatureCompatMethod.call(context, {
				method: 'password',
				token: '123456',
			})

			expect(context.actionHandler.showModal).not.toHaveBeenCalled()
			expect(context.signStore.setSigningErrors).toHaveBeenCalledWith(apiErrors)
			expect(context.loading).toBe(false)
		})

		it('closes password modal when signing fails with non-retriable certificate error', async () => {
			const apiErrors = [{ message: 'Certificate revocation status could not be verified', code: 422 }]
			const context = {
				loading: false,
				elements: [],
				canCreateSignature: false,
				signRequestUuid: 'test-sign-request-uuid',
				signMethodsStore: {
					certificateEngine: 'openssl',
				},
				signatureElementsStore: {
					signs: {},
				},
				actionHandler: {
					showModal: vi.fn(),
					closeModal: vi.fn(),
				},
				signStore: {
					document: { id: 10 },
					clearSigningErrors: vi.fn(),
					setSigningErrors: vi.fn(),
					submitSignature: vi.fn().mockRejectedValue({
						type: 'signError',
						errors: apiErrors,
					}),
				},
				$emit: vi.fn(),
				sidebarStore: {
					hideSidebar: vi.fn(),
				},
			}

			await submitSignatureCompatMethod.call(context, {
				method: 'password',
				token: '123456',
			})

			expect(context.actionHandler.closeModal).toHaveBeenCalledWith('password')
			expect(context.signStore.setSigningErrors).toHaveBeenCalledWith(apiErrors)
			expect(context.loading).toBe(false)
		})

		it('blocks sign CTA and shows explicit retry action when non-retriable error exists', async () => {
			setActivePinia(createPinia())

			const SignComponent = await import('../../../views/SignPDF/_partials/Sign.vue')
			const realSign = SignComponent.default
			const { useSignStore } = await import('../../../store/sign.js')

			const mountedSignStore = useSignStore()
			mountedSignStore.document = createSignDocument({
				status: FILE_STATUS.ABLE_TO_SIGN,
				signers: [{ me: true, status: SIGN_REQUEST_STATUS.ABLE_TO_SIGN, signRequestId: 501 }],
				visibleElements: [],
			})
			mountedSignStore.setSigningErrors([
				{ message: 'Certificate validation failed', code: 422 },
			])

			const wrapper = mount(realSign, {
				global: {
					stubs: {
						NcButton: {
							template: '<button><slot /></button>',
						},
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
						NcNoteCard: {
							template: '<div class="nc-note-card-stub"><slot /></div>',
						},
						NcPasswordField: true,
						NcRichText: {
							props: ['text'],
							template: '<span>{{ text }}</span>',
						},
					},
					mocks: {
						$watch: vi.fn(),
						$nextTick: vi.fn(),
					},
				},
			})

			await wrapper.vm.$nextTick()
			await wrapper.vm.$nextTick()
			await flushPromises()

			expect(wrapper.text()).toContain('Try signing again')
			expect(wrapper.text()).not.toContain('Sign the document.')
			expect(wrapper.findAll('.nc-note-card-stub')).toHaveLength(1)
		})
	})

	describe('proceedWithSigning - Full flow with WhatsApp token', () => {
		let proceedWithSigningLogic: ProceedWithSigningLogic

		beforeEach(() => {
			setActivePinia(createPinia())
			// Function that simulates the proceedWithSigning logic
			proceedWithSigningLogic = (store: SignMethodsStore, actionHandler: ActionHandler) => {
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

			const actionHandler: ActionHandler = {
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

			const actionHandler: ActionHandler = {
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

			const actionHandler: ActionHandler = {
				showModal: vi.fn(),
				closeModal: vi.fn(),
			}

			proceedWithSigningLogic(store, actionHandler)

			expect(actionHandler.showModal).toHaveBeenCalledWith('clickToSign')
		})

		it('does nothing when no signing method is configured', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			const actionHandler: ActionHandler = {
				showModal: vi.fn(),
				closeModal: vi.fn(),
			}

			proceedWithSigningLogic(store, actionHandler)

			expect(actionHandler.showModal).not.toHaveBeenCalled()
		})
	})

	describe('Full signing flow with WhatsApp token', () => {
		let Sign: SignComponentWithConfirm
		let signMethodsStore: SignMethodsStore
		let signStore: SignStore
		let submitSignatureSpy: MockedFunction<(payload: SubmitSignaturePayload) => Promise<unknown>>

		beforeEach(async () => {
			setActivePinia(createPinia())

			// Import stores
			const { useSignStore } = await import('../../../store/sign.js')

			signMethodsStore = useSignMethodsStore() as SignMethodsStore
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
					async signWithTokenCode(
						this: {
							signMethodsStore: SignMethodsStore
							submitSignature: (payload: SubmitSignaturePayload) => Promise<unknown>
						},
						token: string,
					) {
						const tokenMethods: TokenMethodKey[] = ['smsToken', 'whatsappToken', 'signalToken', 'telegramToken', 'xmppToken']
						const activeMethod = tokenMethods.find(method =>
							Object.hasOwn(this.signMethodsStore.settings, method)
						)

						if (!activeMethod) {
							throw new Error('No active token method found')
						}

						const identifyMethod = this.signMethodsStore.settings[activeMethod]?.identifyMethod
						if (!identifyMethod) {
							throw new Error('No identify method found for active token method')
						}

						await this.submitSignature({
							method: identifyMethod,
							token,
						})
					},
					async submitSignature(payload: SubmitSignaturePayload) {
						// Spy on this method
						return submitSignatureSpy(payload)
					},
					confirmSignDocument(this: { signMethodsStore: SignMethodsStore }) {
						// Simulate the logic
						if (this.signMethodsStore.needTokenCode()) {
							this.signMethodsStore.showModal('token')
							return true
						}
						return false
					},
				},
			}

			submitSignatureSpy = vi.fn<(payload: SubmitSignaturePayload) => Promise<unknown>>()
				.mockResolvedValue({ status: 'signed', data: { id: 1 } })
		})

		it('complete flow: click sign button -> token modal opens -> submit token', async () => {
			signMethodsStore.settings = {
				whatsappToken: { needCode: true, identifyMethod: 'whatsapp' },
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
				method: 'whatsapp',
				token: '123456',
			})
		})

		it('complete flow: click sign with multiple token methods enables first available', async () => {
			signMethodsStore.settings = {
				smsToken: { needCode: true, identifyMethod: 'sms' },
				whatsappToken: { needCode: true, identifyMethod: 'whatsapp' },
				telegramToken: { needCode: true, identifyMethod: 'telegram' },
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
				method: 'sms',
				token: '999999',
			})
		})
	})

	describe('signWithTokenCode - Correct identify method from signature methods', () => {
		let Sign: SignComponent
		let signMethodsStore: SignMethodsStore
		let signStore: SignStore
		let submitSignatureSpy: MockedFunction<(payload: SubmitSignaturePayload) => Promise<unknown>>

		beforeEach(async () => {
			setActivePinia(createPinia())

			const { useSignStore } = await import('../../../store/sign.js')

			signMethodsStore = useSignMethodsStore() as SignMethodsStore
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
					async signWithTokenCode(
						this: {
							signMethodsStore: SignMethodsStore
							submitSignature: (payload: SubmitSignaturePayload) => Promise<unknown>
						},
						token: string,
					) {
						const tokenMethods: TokenMethodKey[] = ['smsToken', 'whatsappToken', 'signalToken', 'telegramToken', 'xmppToken']
						const activeMethod = tokenMethods.find(method =>
							Object.hasOwn(this.signMethodsStore.settings, method)
						)

						if (!activeMethod) {
							throw new Error('No active token method found')
						}

						// Extract the identify method from the signature method
						const signatureMethodData = this.signMethodsStore.settings[activeMethod]
						const identifyMethod = signatureMethodData?.identifyMethod
						if (!identifyMethod) {
							throw new Error('No identify method found for active token method')
						}

						await this.submitSignature({
							method: identifyMethod,
							token,
						})
					},
					async submitSignature(payload: SubmitSignaturePayload) {
						return submitSignatureSpy(payload)
					},
				},
			}

			submitSignatureSpy = vi.fn<(payload: SubmitSignaturePayload) => Promise<unknown>>()
				.mockResolvedValue({ status: 'signed' })
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
			const { useSignStore } = await import('../../../store/sign.js')
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
				const signStore = useSignStore()

				// Set up signature method with identify method info
				signMethodsStore.settings = {
					[testCase.signatureMethodKey]: {
						label: testCase.name,
						identifyMethod: testCase.expectedIdentifyMethod,
						needCode: true,
					},
				}

				signStore.document = createSignDocument({
					id: 99,
					signers: [{ me: true, sign_request_uuid: 'test-sign-request-uuid' }],
				})

				const submitSignatureMock = vi.fn().mockResolvedValue({ status: 'signed' })
				signStore.submitSignature = submitSignatureMock as SignStore['submitSignature']

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
							$watch: vi.fn(),
							$nextTick: vi.fn(),
						},
					},
					props: {},
				})

				// Call real signWithTokenCode method
				await wrapper.vm.signWithTokenCode(testCase.token)

				// VERIFY: Must send identify method, NOT signature method name
				expect(submitSignatureMock).toHaveBeenCalledWith({
					method: testCase.expectedIdentifyMethod,
					token: testCase.token,
				}, 'test-sign-request-uuid', {
					documentId: 99,
				})

				// Double-check: Should NOT send the signature method key name
				expect(submitSignatureMock).not.toHaveBeenCalledWith({
					method: testCase.signatureMethodKey,
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

			signStore.document = createSignDocument({
				nodeType: 'envelope',
				signers: [],
				files: [
					{
						id: 10,
						name: 'child-file',
						signers: [
							{ signRequestId: 501, me: true },
						],
						visibleElements: [
							{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10, top: 20, width: 30, height: 40 } },
						],
					},
				],
			})

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
						$watch: vi.fn(),
						$nextTick: vi.fn(),
					},
				},
			})

			expect(wrapper.vm.elements).toEqual([
				{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10, top: 20, width: 30, height: 40 } },
			])
		})

		it('keeps numeric ids and drops incomplete visible elements', async () => {
			setActivePinia(createPinia())

			const SignComponent = await import('../../../views/SignPDF/_partials/Sign.vue')
			const realSign = SignComponent.default
			const { useSignStore } = await import('../../../store/sign.js')
			const { useSignatureElementsStore } = await import('../../../store/signatureElements.js')

			const signStore = useSignStore()
			const signatureElementsStore = useSignatureElementsStore()

			signStore.document = createSignDocument({
				nodeType: 'file',
				signers: [
					{ signRequestId: 501, me: true },
				],
				visibleElements: [
					{ elementId: 201, fileId: 1, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10, top: 20, width: 30, height: 40 } },
					{ fileId: 1, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 99, top: 88, width: 20, height: 10 } },
				],
			})

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
						$watch: vi.fn(),
						$nextTick: vi.fn(),
					},
				},
			})

			expect(wrapper.vm.elements).toEqual([
				{ elementId: 201, fileId: 1, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10, top: 20, width: 30, height: 40 } },
			])
		})

		it('updates elements when signature is created dynamically', async () => {
			const { default: realSign } = await import('../../../views/SignPDF/_partials/Sign.vue')
			const { useSignStore } = await import('../../../store/sign.js')
			const { useSignatureElementsStore } = await import('../../../store/signatureElements.js')

			const signStore = useSignStore()
			const signatureElementsStore = useSignatureElementsStore()

			signStore.document = createSignDocument({
				nodeType: 'envelope',
				signers: [
					{ signRequestId: 501, me: true },
				],
				files: [],
				visibleElements: [
					{ elementId: 201, fileId: 1, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10, top: 20, width: 30, height: 40 } },
				],
			})

			// Initially, no signature exists
			signatureElementsStore.signs.signature = {
				id: 0,
				type: '',
				file: { url: '', nodeId: 0 },
				starred: 0,
				createdAt: '', // Empty createdAt means no signature
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
						$watch: vi.fn(),
					},
				},
			})

			// Initially, elements should be empty (no signature created)
			expect(wrapper.vm.elements).toEqual([])
			expect(wrapper.vm.hasSignatures).toBe(false)
			expect(wrapper.vm.needCreateSignature).toBe(true)

			// Now simulate creating a signature (like when user draws one)
			signatureElementsStore.signs.signature = {
				id: 1,
				type: 'signature',
				file: { url: '/sig.png', nodeId: 11623 },
				starred: 0,
				createdAt: '2024-01-01', // Now has a createdAt, signature exists
			}

			// Force Vue to update
			await wrapper.vm.$nextTick()

			// After signature is created, elements should include it
			expect(wrapper.vm.elements).toEqual([
				{ elementId: 201, fileId: 1, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10, top: 20, width: 30, height: 40 } },
			])
			expect(wrapper.vm.hasSignatures).toBe(true)
			expect(wrapper.vm.needCreateSignature).toBe(false)
		})

		it('requires signature creation for envelope child elements even when parent signer id differs', async () => {
			const { default: realSign } = await import('../../../views/SignPDF/_partials/Sign.vue')
			const { useSignStore } = await import('../../../store/sign.js')
			const { useSignatureElementsStore } = await import('../../../store/signatureElements.js')

			const signStore = useSignStore()
			const signatureElementsStore = useSignatureElementsStore()

			signStore.document = createSignDocument({
				nodeType: 'envelope',
				signers: [
					{ signRequestId: 700, me: true },
				],
				files: [
					{
						id: 10,
						name: 'child-file',
						signers: [
							{ signRequestId: 501, me: true },
						],
						visibleElements: [
							{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10, top: 20, width: 30, height: 40 } },
						],
					},
				],
			})

			signatureElementsStore.signs.signature = {
				id: 0,
				type: '',
				file: { url: '', nodeId: 0 },
				starred: 0,
				createdAt: '',
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
						$watch: vi.fn(),
					},
				},
			})

			expect(wrapper.vm.hasSignatures).toBe(false)
			expect(wrapper.vm.needCreateSignature).toBe(true)
		})

		it('returns false for needCreateSignature when signer has no placed visibleElements (clickToSign scenario)', async () => {
			// Regression: commit e9ea79495 removed visibleElements.some() check, causing
			// needCreateSignature to return true for clickToSign documents where no visual
			// element box was placed — hiding the "Sign the document." button.
			const { default: realSign } = await import('../../../views/SignPDF/_partials/Sign.vue')
			const { useSignStore } = await import('../../../store/sign.js')

			const signStore = useSignStore()

			// Signer has signRequestId but NO placed visibleElements (typical clickToSign)
			signStore.document = createSignDocument({
				nodeType: 'file',
				signers: [
					{ signRequestId: 501, me: true },
				],
				visibleElements: [],
			})

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
						$watch: vi.fn(),
					},
				},
			})

			// canCreateSignature is true (mocked globally), but no element was placed for
			// this signer, so we must NOT show "Define your signature" — the "Sign the
			// document." button should be reachable.
			expect(wrapper.vm.canCreateSignature).toBe(true)
			expect(wrapper.vm.hasSignatures).toBe(false)
			expect(wrapper.vm.needCreateSignature).toBe(false)
		})
	})

	describe('Sign.vue - create signature modal', () => {
		it('renders Draw editor when createSignature modal is active', async () => {
			setActivePinia(createPinia())

			const SignComponent = await import('../../../views/SignPDF/_partials/Sign.vue')
			const realSign = SignComponent.default
			const signMethodsStore = useSignMethodsStore()
			const { useSignStore } = await import('../../../store/sign.js')
			const signStore = useSignStore()
			signStore.document = createSignDocument()
			signMethodsStore.showModal('createSignature')

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
						Draw: {
							name: 'Draw',
							props: ['drawEditor', 'textEditor', 'fileEditor', 'type'],
							template: '<div class="draw-editor-stub" />',
						},
						ManagePassword: true,
						CreatePassword: true,
						NcNoteCard: true,
						NcPasswordField: true,
						NcRichText: true,
					},
					mocks: {
						$watch: vi.fn(),
						$nextTick: vi.fn(),
					},
				},
			})

			expect(wrapper.find('.draw-editor-stub').exists()).toBe(true)
			const draw = wrapper.findComponent({ name: 'Draw' })
			expect(draw.props('drawEditor')).toBe(true)
			expect(draw.props('textEditor')).toBe(true)
			expect(draw.props('fileEditor')).toBe(true)
			expect(draw.props('type')).toBe('signature')
		})
	})
})

	describe('Sign.vue - envelope multi-file signing (issue #7344 phase 2)', () => {
		let submitSignatureCompatMethod: SubmitSignatureCompatMethod

		beforeAll(async () => {
			const SignComponent = await import('../../../views/SignPDF/_partials/Sign.vue')
			submitSignatureCompatMethod = (SignComponent.default as any).methods.submitSignature
		})

		it('calls signStore.submitSignature once per file for envelopes with multiple me=true signers', async () => {
			const storeSubmitMock = vi.fn().mockResolvedValue({ status: 'signed', data: {} })

			const context = {
				loading: false,
				elements: [
					{ elementId: 100, signRequestId: 10, type: 'signature' },
					{ elementId: 200, signRequestId: 20, type: 'signature' },
				],
				canCreateSignature: false,
				signRequestUuid: 'uuid-file-1',
				signatureElementsStore: { signs: {} },
				actionHandler: { showModal: vi.fn(), closeModal: vi.fn() },
				signStore: {
					document: {
						id: 1,
						nodeType: 'envelope',
						signers: [
							{ signRequestId: 10, me: true, sign_request_uuid: 'uuid-file-1' },
							{ signRequestId: 20, me: true, sign_request_uuid: 'uuid-file-2' },
						],
					},
					clearSigningErrors: vi.fn(),
					setSigningErrors: vi.fn(),
					submitSignature: storeSubmitMock,
				},
				$emit: vi.fn(),
				sidebarStore: { hideSidebar: vi.fn() },
			}

			await submitSignatureCompatMethod.call(context, { method: 'clickToSign' })

			expect(storeSubmitMock).toHaveBeenCalledTimes(2)
			expect(storeSubmitMock).toHaveBeenNthCalledWith(
				1,
				{ method: 'clickToSign', elements: [{ documentElementId: 100 }] },
				'uuid-file-1',
				{ documentId: 1 },
			)
			expect(storeSubmitMock).toHaveBeenNthCalledWith(
				2,
				{ method: 'clickToSign', elements: [{ documentElementId: 200 }] },
				'uuid-file-2',
				{ documentId: 1 },
			)
		})

		it('submits each file without elements when no visible elements are placed (click-to-sign envelope)', async () => {
			const storeSubmitMock = vi.fn().mockResolvedValue({ status: 'signed', data: {} })

			const context = {
				loading: false,
				elements: [],
				canCreateSignature: false,
				signRequestUuid: 'uuid-file-1',
				signatureElementsStore: { signs: {} },
				actionHandler: { showModal: vi.fn(), closeModal: vi.fn() },
				signStore: {
					document: {
						id: 1,
						nodeType: 'envelope',
						signers: [
							{ signRequestId: 10, me: true, sign_request_uuid: 'uuid-file-1' },
							{ signRequestId: 20, me: true, sign_request_uuid: 'uuid-file-2' },
						],
					},
					clearSigningErrors: vi.fn(),
					setSigningErrors: vi.fn(),
					submitSignature: storeSubmitMock,
				},
				$emit: vi.fn(),
				sidebarStore: { hideSidebar: vi.fn() },
			}

			await submitSignatureCompatMethod.call(context, { method: 'clickToSign' })

			expect(storeSubmitMock).toHaveBeenCalledTimes(2)
			expect(storeSubmitMock).toHaveBeenNthCalledWith(
				1,
				{ method: 'clickToSign' },
				'uuid-file-1',
				{ documentId: 1 },
			)
			expect(storeSubmitMock).toHaveBeenNthCalledWith(
				2,
				{ method: 'clickToSign' },
				'uuid-file-2',
				{ documentId: 1 },
			)
		})

		it('preserves single-file behavior when document nodeType is not envelope', async () => {
			const storeSubmitMock = vi.fn().mockResolvedValue({ status: 'signed', data: {} })

			const context = {
				loading: false,
				elements: [
					{ elementId: 100, signRequestId: 10, type: 'signature' },
				],
				canCreateSignature: false,
				signRequestUuid: 'uuid-file-1',
				signatureElementsStore: { signs: {} },
				actionHandler: { showModal: vi.fn(), closeModal: vi.fn() },
				signStore: {
					document: {
						id: 1,
						nodeType: 'file',
						signers: [
							{ signRequestId: 10, me: true, sign_request_uuid: 'uuid-file-1' },
						],
					},
					clearSigningErrors: vi.fn(),
					setSigningErrors: vi.fn(),
					submitSignature: storeSubmitMock,
				},
				$emit: vi.fn(),
				sidebarStore: { hideSidebar: vi.fn() },
			}

			await submitSignatureCompatMethod.call(context, { method: 'clickToSign' })

			expect(storeSubmitMock).toHaveBeenCalledTimes(1)
			expect(storeSubmitMock).toHaveBeenCalledWith(
				{ method: 'clickToSign', elements: [{ documentElementId: 100 }] },
				'uuid-file-1',
				{ documentId: 1 },
			)
		})

		it('includes profileNodeId per element when canCreateSignature is true for envelope', async () => {
			const storeSubmitMock = vi.fn().mockResolvedValue({ status: 'signed', data: {} })

			const context = {
				loading: false,
				elements: [
					{ elementId: 100, signRequestId: 10, type: 'signature' },
					{ elementId: 200, signRequestId: 20, type: 'signature' },
				],
				canCreateSignature: true,
				signRequestUuid: 'uuid-file-1',
				signatureElementsStore: {
					signs: {
						signature: { file: { nodeId: 42 } },
					},
				},
				actionHandler: { showModal: vi.fn(), closeModal: vi.fn() },
				signStore: {
					document: {
						id: 1,
						nodeType: 'envelope',
						signers: [
							{ signRequestId: 10, me: true, sign_request_uuid: 'uuid-file-1' },
							{ signRequestId: 20, me: true, sign_request_uuid: 'uuid-file-2' },
						],
					},
					clearSigningErrors: vi.fn(),
					setSigningErrors: vi.fn(),
					submitSignature: storeSubmitMock,
				},
				$emit: vi.fn(),
				sidebarStore: { hideSidebar: vi.fn() },
			}

			await submitSignatureCompatMethod.call(context, { method: 'clickToSign' })

			expect(storeSubmitMock).toHaveBeenCalledTimes(2)
			expect(storeSubmitMock).toHaveBeenNthCalledWith(
				1,
				{ method: 'clickToSign', elements: [{ documentElementId: 100, profileNodeId: 42 }] },
				'uuid-file-1',
				{ documentId: 1 },
			)
			expect(storeSubmitMock).toHaveBeenNthCalledWith(
				2,
				{ method: 'clickToSign', elements: [{ documentElementId: 200, profileNodeId: 42 }] },
				'uuid-file-2',
				{ documentId: 1 },
			)
		})
	})
