/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useSignMethodsStore } from '../../store/signMethods.js'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: () => 'none',
}))

describe('signMethods store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('initializes with default settings', () => {
		const store = useSignMethodsStore()
		store.settings = {}

		expect(store.settings).toEqual({})
		expect(store.modal).toBeDefined()
	})

	it('shows and closes modals', () => {
		const store = useSignMethodsStore()

		store.showModal('password')
		expect(store.modal.password).toBe(true)

		store.closeModal('password')
		expect(store.modal.password).toBe(false)
	})

	it('checks if password is needed', () => {
		const store = useSignMethodsStore()
		store.settings = { password: {} }

		const result = store.needSignWithPassword()
		expect(typeof result).toBe('boolean')
	})

	it('checks if email code is needed', () => {
		const store = useSignMethodsStore()
		store.settings = {
			emailToken: { needCode: true },
		}

		expect(store.needEmailCode()).toBe(true)
	})

	it('checks if token code is needed', () => {
		const store = useSignMethodsStore()
		store.settings = {
			smsToken: { needCode: true },
		}

		expect(store.needTokenCode()).toBe(true)
	})

	it('sets signature file flag', () => {
		const store = useSignMethodsStore()

		store.setHasSignatureFile(true)
		expect(store.hasSignatureFile()).toBe(true)
	})

	it('checks if certificate is needed', () => {
		const store = useSignMethodsStore()
		store.settings = {
			password: { hasSignatureFile: false },
		}
		const result = store.needCertificate()

		expect(typeof result).toBe('boolean')
	})

	describe('needCreatePassword rules', () => {
		it('returns true when password needed but no signature file', () => {
			const store = useSignMethodsStore()
			store.settings = {
				password: { hasSignatureFile: false },
			}

			expect(store.needCreatePassword()).toBe(true)
		})

		it('returns false when password needed and has signature file', () => {
			const store = useSignMethodsStore()
			store.settings = {
				password: { hasSignatureFile: true },
			}

			expect(store.needCreatePassword()).toBe(false)
		})

		it('returns false when password not needed', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			expect(store.needCreatePassword()).toBe(false)
		})
	})

	describe('needSignWithPassword rules', () => {
		it('returns true when password config exists', () => {
			const store = useSignMethodsStore()
			store.settings = { password: {} }

			expect(store.needSignWithPassword()).toBe(true)
		})

		it('returns true with any password configuration', () => {
			const store = useSignMethodsStore()
			store.settings = { password: { hasSignatureFile: true } }

			expect(store.needSignWithPassword()).toBe(true)
		})

		it('returns false when no password config', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			expect(store.needSignWithPassword()).toBe(false)
		})
	})

	describe('needCertificate rules', () => {
		it('returns true when engine is none and no signature file', () => {
			const store = useSignMethodsStore()
			store.certificateEngine = 'none'
			store.settings = {
				password: { hasSignatureFile: false },
			}

			expect(store.needCertificate()).toBe(true)
		})

		it('returns false when engine is none but has signature file', () => {
			const store = useSignMethodsStore()
			store.certificateEngine = 'none'
			store.settings = {
				password: { hasSignatureFile: true },
			}

			expect(store.needCertificate()).toBe(false)
		})

		it('returns false when engine is openssl', () => {
			const store = useSignMethodsStore()
			store.certificateEngine = 'openssl'
			store.settings = {}

			expect(store.needCertificate()).toBe(false)
		})

		it('returns false when engine is cfssl', () => {
			const store = useSignMethodsStore()
			store.certificateEngine = 'cfssl'
			store.settings = {}

			expect(store.needCertificate()).toBe(false)
		})
	})

	describe('needEmailCode rules', () => {
		it('returns true when emailToken has needCode true', () => {
			const store = useSignMethodsStore()
			store.settings = {
				emailToken: { needCode: true },
			}

			expect(store.needEmailCode()).toBe(true)
		})

		it('returns false when emailToken has needCode false', () => {
			const store = useSignMethodsStore()
			store.settings = {
				emailToken: { needCode: false },
			}

			expect(store.needEmailCode()).toBe(false)
		})

		it('returns false when emailToken not configured', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			expect(store.needEmailCode()).toBe(false)
		})

		it('preserves other emailToken properties', () => {
			const store = useSignMethodsStore()
			store.settings = {
				emailToken: { needCode: true, blurredEmail: 'te***@example.com' },
			}

			expect(store.needEmailCode()).toBe(true)
			expect(store.settings.emailToken.blurredEmail).toBe('te***@example.com')
		})
	})

	describe('needTokenCode rules', () => {
		it('detects SMS token requirement', () => {
			const store = useSignMethodsStore()
			store.settings = { smsToken: { needCode: true } }

			expect(store.needTokenCode()).toBe(true)
		})

		it('detects WhatsApp token requirement', () => {
			const store = useSignMethodsStore()
			store.settings = { whatsappToken: { needCode: true } }

			expect(store.needTokenCode()).toBe(true)
		})

		it('detects Signal token requirement', () => {
			const store = useSignMethodsStore()
			store.settings = { signalToken: { needCode: true } }

			expect(store.needTokenCode()).toBe(true)
		})

		it('detects Telegram token requirement', () => {
			const store = useSignMethodsStore()
			store.settings = { telegramToken: { needCode: true } }

			expect(store.needTokenCode()).toBe(true)
		})

		it('detects XMPP token requirement', () => {
			const store = useSignMethodsStore()
			store.settings = { xmppToken: { needCode: true } }

			expect(store.needTokenCode()).toBe(true)
		})

		it('returns false when no token code needed', () => {
			const store = useSignMethodsStore()
			store.settings = {
				smsToken: { needCode: false },
				whatsappToken: { needCode: false },
			}

			expect(store.needTokenCode()).toBe(false)
		})

		it('returns false with empty settings', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			expect(store.needTokenCode()).toBe(false)
		})

		it('returns true if any token method needs code', () => {
			const store = useSignMethodsStore()
			store.settings = {
				smsToken: { needCode: false },
				whatsappToken: { needCode: false },
				telegramToken: { needCode: true },
			}

			expect(store.needTokenCode()).toBe(true)
		})
	})

	describe('needClickToSign rules', () => {
		it('returns true when clickToSign is configured', () => {
			const store = useSignMethodsStore()
			store.settings = { clickToSign: {} }

			expect(store.needClickToSign()).toBe(true)
		})

		it('returns true with any clickToSign value', () => {
			const store = useSignMethodsStore()
			store.settings = { clickToSign: { enabled: true } }

			expect(store.needClickToSign()).toBe(true)
		})

		it('returns false when clickToSign not configured', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			expect(store.needClickToSign()).toBe(false)
		})
	})

	describe('needSmsCode rules', () => {
		it('returns true when SMS needs code', () => {
			const store = useSignMethodsStore()
			store.settings = { smsToken: { needCode: true } }

			expect(store.needSmsCode()).toBe(true)
		})

		it('returns false when SMS does not need code', () => {
			const store = useSignMethodsStore()
			store.settings = { smsToken: { needCode: false } }

			expect(store.needSmsCode()).toBe(false)
		})

		it('returns false when SMS not configured', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			expect(store.needSmsCode()).toBe(false)
		})
	})

	describe('hasSignatureFile rules', () => {
		it('returns true when password has signature file', () => {
			const store = useSignMethodsStore()
			store.settings = {
				password: { hasSignatureFile: true },
			}

			expect(store.hasSignatureFile()).toBe(true)
		})

		it('returns false when password has no signature file', () => {
			const store = useSignMethodsStore()
			store.settings = {
				password: { hasSignatureFile: false },
			}

			expect(store.hasSignatureFile()).toBe(false)
		})

		it('returns false when password not configured', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			expect(store.hasSignatureFile()).toBe(false)
		})
	})

	describe('modal state management', () => {
		it('initializes all modals as false', () => {
			const store = useSignMethodsStore()

			expect(store.modal.emailToken).toBe(false)
			expect(store.modal.clickToSign).toBe(false)
			expect(store.modal.createPassword).toBe(false)
			expect(store.modal.signPassword).toBe(false)
			expect(store.modal.createSignature).toBe(false)
			expect(store.modal.password).toBe(false)
			expect(store.modal.token).toBe(false)
			expect(store.modal.uploadCertificate).toBe(false)
		})

		it('toggles individual modals independently', () => {
			const store = useSignMethodsStore()

			store.showModal('password')
			expect(store.modal.password).toBe(true)
			expect(store.modal.emailToken).toBe(false)

			store.showModal('emailToken')
			expect(store.modal.emailToken).toBe(true)
			expect(store.modal.password).toBe(true)

			store.closeModal('password')
			expect(store.modal.password).toBe(false)
			expect(store.modal.emailToken).toBe(true)
		})

		it('shows multiple modals simultaneously', () => {
			const store = useSignMethodsStore()

			store.showModal('createPassword')
			store.showModal('uploadCertificate')

			expect(store.modal.createPassword).toBe(true)
			expect(store.modal.uploadCertificate).toBe(true)
		})
	})

	describe('email token methods', () => {
		it('sets email confirm code flag', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			store.setHasEmailConfirmCode(true)

			expect(store.settings.emailToken?.hasConfirmCode).toBe(true)
		})

		it('creates emailToken object if not exists', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			store.setHasEmailConfirmCode(true)

			expect(store.settings.emailToken).toBeDefined()
		})

		it('sets email token value', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			store.setEmailToken('token123')

			expect(store.settings.emailToken?.token).toBe('token123')
		})

		it('preserves existing emailToken properties when setting token', () => {
			const store = useSignMethodsStore()
			store.settings = {
				emailToken: { needCode: true, blurredEmail: 'te***@example.com' },
			}

			store.setEmailToken('newtoken')

			expect(store.settings.emailToken.token).toBe('newtoken')
			expect(store.settings.emailToken.needCode).toBe(true)
			expect(store.settings.emailToken.blurredEmail).toBe('te***@example.com')
		})

		it('returns blurred email when available', () => {
			const store = useSignMethodsStore()
			store.settings = {
				emailToken: { blurredEmail: 'te***@example.com' },
			}

			expect(store.blurredEmail()).toBe('te***@example.com')
		})

		it('returns empty string when blurred email not available', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			expect(store.blurredEmail()).toBe('')
		})
	})

	describe('signature file management', () => {
		it('initializes signature file flag as undefined', () => {
			const store = useSignMethodsStore()

			expect(store.hasSignatureFile()).toBe(false)
		})

		it('sets signature file flag correctly', () => {
			const store = useSignMethodsStore()

			store.setHasSignatureFile(true)
			expect(store.hasSignatureFile()).toBe(true)

			store.setHasSignatureFile(false)
			expect(store.hasSignatureFile()).toBe(false)
		})

		it('creates password object if not exists', () => {
			const store = useSignMethodsStore()
			store.settings = {}

			store.setHasSignatureFile(true)

			expect(store.settings.password).toBeDefined()
		})

		it('preserves other password properties', () => {
			const store = useSignMethodsStore()
			store.settings = {
				password: { someOtherProp: 'value' },
			}

			store.setHasSignatureFile(true)

			expect(store.settings.password.someOtherProp).toBe('value')
			expect(store.settings.password.hasSignatureFile).toBe(true)
		})
	})
})
