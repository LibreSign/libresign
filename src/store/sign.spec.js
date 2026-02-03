/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useSignStore } from './sign.js'
import { FILE_STATUS } from '../constants.js'

describe('useSignStore', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	describe('pendingAction', () => {
		it('initializes pendingAction as null', () => {
			const store = useSignStore()
			expect(store.pendingAction).toBe(null)
		})

		it('sets pendingAction when queueAction is called', () => {
			const store = useSignStore()
			store.queueAction('sign')
			expect(store.pendingAction).toBe('sign')
		})

		it('clears pendingAction when clearPendingAction is called', () => {
			const store = useSignStore()
			store.queueAction('sign')
			store.clearPendingAction()
			expect(store.pendingAction).toBe(null)
		})

		it('allows queuing different action types', () => {
			const store = useSignStore()

			store.queueAction('sign')
			expect(store.pendingAction).toBe('sign')

			store.queueAction('createSignature')
			expect(store.pendingAction).toBe('createSignature')

			store.queueAction('createPassword')
			expect(store.pendingAction).toBe('createPassword')
		})
	})

	describe('ableToSign getter', () => {
		it('returns false when document status is not ABLE_TO_SIGN or PARTIAL_SIGNED', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.DRAFT,
				signers: [{ me: true, status: 1 }],
			}
			expect(store.ableToSign).toBe(false)
		})

		it('returns false when there is no signer with me: true', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.ABLE_TO_SIGN,
				signers: [{ me: false, status: 1 }],
			}
			expect(store.ableToSign).toBe(false)
		})

		it('returns false when signer status is not ABLE_TO_SIGN (status 1)', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.ABLE_TO_SIGN,
				signers: [{ me: true, status: 0 }],
			}
			expect(store.ableToSign).toBe(false)
		})

		it('returns true when document status is ABLE_TO_SIGN and signer can sign', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.ABLE_TO_SIGN,
				signers: [{ me: true, status: 1 }],
			}
			expect(store.ableToSign).toBe(true)
		})

		it('returns true when document status is PARTIAL_SIGNED and signer can sign', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.PARTIAL_SIGNED,
				signers: [{ me: true, status: 1 }],
			}
			expect(store.ableToSign).toBe(true)
		})

		it('returns false when document is undefined', () => {
			const store = useSignStore()
			store.document = undefined
			expect(store.ableToSign).toBe(false)
		})

		it('returns false when signers array is empty', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.ABLE_TO_SIGN,
				signers: [],
			}
			expect(store.ableToSign).toBe(false)
		})
	})
})
