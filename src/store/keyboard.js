/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Observe various events and save the current
 * special keys states. Useful for checking the
 * current status of a key when executing a method.
 * @param {...any} args properties
 */
export const useKeyboardStore = function(...args) {
	const keyboardStore = _keyboardStore(...args)
	if (!_initialized) {
		window.addEventListener('keydown', keyboardStore.onEvent)
		window.addEventListener('keyup', keyboardStore.onEvent)
		window.addEventListener('mousemove', keyboardStore.onEvent)
		_initialized = true
	}
	return keyboardStore
}

const _keyboardStore = defineStore('keyboard', () => {
	const altKey = ref(false)
	const ctrlKey = ref(false)
	const metaKey = ref(false)
	const shiftKey = ref(false)

	const onEvent = (event) => {
		if (!event) {
			event = window.event
		}
		altKey.value = !!event.altKey
		ctrlKey.value = !!event.ctrlKey
		metaKey.value = !!event.metaKey
		shiftKey.value = !!event.shiftKey
	}

	return {
		altKey,
		ctrlKey,
		metaKey,
		shiftKey,
		onEvent,
	}
})

let _initialized = false
