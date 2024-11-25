/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { defineStore } from 'pinia'
import { set } from 'vue'

/**
 * Observe various events and save the current
 * special keys states. Useful for checking the
 * current status of a key when executing a method.
 * @param {...any} args properties
 */
export const useKeyboardStore = function(...args) {
	const store = defineStore('keyboard', {
		state: () => ({
			altKey: false,
			ctrlKey: false,
			metaKey: false,
			shiftKey: false,
		}),

		actions: {
			onEvent(event) {
				if (!event) {
					event = window.event
				}
				set(this, 'altKey', !!event.altKey)
				set(this, 'ctrlKey', !!event.ctrlKey)
				set(this, 'metaKey', !!event.metaKey)
				set(this, 'shiftKey', !!event.shiftKey)
			},
		},
	})

	const keyboardStore = store(...args)
	// Make sure we only register the listeners once
	if (!keyboardStore._initialized) {
		window.addEventListener('keydown', keyboardStore.onEvent)
		window.addEventListener('keyup', keyboardStore.onEvent)
		window.addEventListener('mousemove', keyboardStore.onEvent)

		keyboardStore._initialized = true
	}

	return keyboardStore
}
