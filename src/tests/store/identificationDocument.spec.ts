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

	it('is document pending when enabled', () => {
		const store = useIdentificationDocumentStore()

		store.setEnabled(true)

		expect(store.isDocumentPending()).toBe(true)
	})

	it('is not document pending when disabled', () => {
		const store = useIdentificationDocumentStore()

		store.setEnabled(false)

		expect(store.isDocumentPending()).toBe(false)
	})

	it('shows documents component when enabled', () => {
		const store = useIdentificationDocumentStore()

		store.setEnabled(true)

		expect(store.showDocumentsComponent()).toBe(true)
	})

	it('does not show documents component when disabled', () => {
		const store = useIdentificationDocumentStore()

		store.setEnabled(false)

		expect(store.showDocumentsComponent()).toBe(false)
	})

	it('needs identification document when enabled but not waiting', () => {
		const store = useIdentificationDocumentStore()

		store.setEnabled(true)
		store.setWaitingApproval(false)

		expect(store.needIdentificationDocument()).toBe(true)
	})

	it('needs identification document when waiting for approval', () => {
		const store = useIdentificationDocumentStore()

		store.setEnabled(false)
		store.setWaitingApproval(true)

		expect(store.needIdentificationDocument()).toBe(true)
	})

	it('does not need identification document when disabled and not waiting', () => {
		const store = useIdentificationDocumentStore()

		store.setEnabled(false)
		store.setWaitingApproval(false)

		expect(store.needIdentificationDocument()).toBe(false)
	})

	it('toggles modal visibility', () => {
		const store = useIdentificationDocumentStore()

		store.showModal()
		expect(store.modal).toBe(true)

		store.closeModal()
		expect(store.modal).toBe(false)
	})
})
