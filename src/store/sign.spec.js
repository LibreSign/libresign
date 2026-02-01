/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useSignStore } from './sign.js'

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
})
