/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useIdentificationDocumentStore } from '../../store/identificationDocument.js'


vi.mock('@nextcloud/initial-state', () => ({
	loadState: () => false,
}))

describe('identificationDocument store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('requires identification document when enabled and not waiting approval', () => {
		const store = useIdentificationDocumentStore()

		store.setEnabled(true)
		store.setWaitingApproval(false)

		expect(store.needIdentificationDocument()).toBe(true)
	})

	it('does not require identification document when waiting approval', () => {
		const store = useIdentificationDocumentStore()

		store.setEnabled(true)
		store.setWaitingApproval(true)

		expect(store.needIdentificationDocument()).toBe(true)
	})

	it('toggles modal visibility', () => {
		const store = useIdentificationDocumentStore()

		store.showModal()
		expect(store.modal).toBe(true)

		store.closeModal()
		expect(store.modal).toBe(false)
	})
})
