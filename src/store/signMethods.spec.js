/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useSignMethodsStore } from './signMethods.js'

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
			sms: { needCode: true },
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
})
