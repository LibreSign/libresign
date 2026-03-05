/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useKeyboardStore } from '../../store/keyboard.js'

type KeyboardLikeEvent = Pick<KeyboardEvent, 'altKey' | 'ctrlKey' | 'metaKey' | 'shiftKey'>

describe('keyboard store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('updates key states from event', () => {
		const store = useKeyboardStore()

		store.onEvent({ altKey: true, ctrlKey: false, metaKey: true, shiftKey: false })

		expect(store.altKey).toBe(true)
		expect(store.ctrlKey).toBe(false)
		expect(store.metaKey).toBe(true)
		expect(store.shiftKey).toBe(false)
	})

	it('falls back to window.event when no event provided', () => {
		const store = useKeyboardStore()

		const fallbackEvent: KeyboardLikeEvent = { altKey: false, ctrlKey: true, metaKey: false, shiftKey: true }
		;(window as unknown as { event?: KeyboardLikeEvent }).event = fallbackEvent
		store.onEvent()

		expect(store.altKey).toBe(false)
		expect(store.ctrlKey).toBe(true)
		expect(store.metaKey).toBe(false)
		expect(store.shiftKey).toBe(true)
	})
})
