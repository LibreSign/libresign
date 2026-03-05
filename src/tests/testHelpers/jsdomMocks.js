/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Mocks for browser APIs missing or broken in jsdom

/**
 * document.readyState mock - @nextcloud/logger checks this during import
 * Note: In vmForks pool, each test file runs in isolated context.
 * Tests importing @nextcloud/vue components should mock @nextcloud/logger directly.
 */
if (typeof document !== 'undefined') {
	Object.defineProperty(document, 'readyState', {
		value: 'complete',
		writable: true,
		configurable: true,
	})

	// Ensure document.body exists - @nextcloud/vue isDarkTheme checks it during import
	if (!document.body) {
		document.body = document.createElement('body')
		document.documentElement.appendChild(document.body)
	}
}

/**
 * localStorage mock - jsdom creates it as null
 * Must be here because some modules access it at import-time
 */
if (typeof window !== 'undefined') {
	const store = {}
	const localStorage = {
		getItem: (key) => store[key] || null,
		setItem: (key, value) => { store[key] = String(value) },
		removeItem: (key) => { delete store[key] },
		clear: () => { Object.keys(store).forEach(key => delete store[key]) },
		get length() { return Object.keys(store).length },
		key: (index) => Object.keys(store)[index] || null,
	}

	Object.defineProperty(window, 'localStorage', {
		value: localStorage,
		writable: true,
		configurable: true,
	})

	// Mock window.location - @nextcloud/vue components may access it during import
	if (!window.location || !window.location.href) {
		Object.defineProperty(window, 'location', {
			value: {
				href: 'http://localhost/',
				origin: 'http://localhost',
				protocol: 'http:',
				host: 'localhost',
				hostname: 'localhost',
				port: '',
				pathname: '/',
				search: '',
				hash: '',
			},
			writable: true,
			configurable: true,
		})
	}

	// Mock navigator properties - @nextcloud/vue components may check locale/language
	if (typeof navigator !== 'undefined') {
		if (!navigator.language) {
			Object.defineProperty(navigator, 'language', {
				value: 'en-US',
				writable: true,
				configurable: true,
			})
		}
		if (!navigator.languages) {
			Object.defineProperty(navigator, 'languages', {
				value: ['en-US', 'en'],
				writable: true,
				configurable: true,
			})
		}
	}
}

if (typeof HTMLCanvasElement !== 'undefined') {
	HTMLCanvasElement.prototype.getContext = HTMLCanvasElement.prototype.getContext || function getContext() {
		return {
			clearRect() {},
			fillRect() {},
			fillText() {},
			measureText() { return { width: 0 } },
			beginPath() {},
			moveTo() {},
			lineTo() {},
			stroke() {},
			setTransform() {},
			save() {},
			restore() {},
		}
	}
}

if (typeof window !== 'undefined') {
	class MutationObserverMock {
		constructor(callback) {
			this._callback = callback
		}
		observe() {}
		disconnect() {}
		takeRecords() {
			return []
		}
	}
	window.MutationObserver = MutationObserverMock
	globalThis.MutationObserver = MutationObserverMock
}

