/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useSignMethodsStore } from './signMethods.js'

const loadStateMock = () => 'none'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: () => loadStateMock(),
}))

describe('signMethods store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('requires creating password when signing with password and no signature file', () => {
		const store = useSignMethodsStore()

		store.settings.password = {}

		expect(store.needSignWithPassword()).toBe(true)
		expect(store.needCreatePassword()).toBe(true)
	})

	it('does not require password creation when signature file exists', () => {
		const store = useSignMethodsStore()

		store.settings.password = {}
		store.setHasSignatureFile(true)

		expect(store.needCreatePassword()).toBe(false)
	})

	it('detects token code requirement from configured methods', () => {
		const store = useSignMethodsStore()

		store.settings.sms = { needCode: true }

		expect(store.needTokenCode()).toBe(true)
	})

	it('requires certificate when engine is none and no signature file', () => {
		const store = useSignMethodsStore()

		expect(store.needCertificate()).toBe(true)
	})

	it('does not require certificate when engine is configured', () => {
		const store = useSignMethodsStore()

		store.certificateEngine = 'openssl'

		expect(store.needCertificate()).toBe(false)
	})
})
