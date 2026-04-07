/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

type LibreSignRuntimeConfig = {
	nextcloudUrl?: string
	openMode?: 'new-tab' | 'same-tab'
}

declare global {
	interface Window {
		__LIBRESIGN_CONFIG__?: LibreSignRuntimeConfig
	}
}

window.__LIBRESIGN_CONFIG__ = {
	...(window.__LIBRESIGN_CONFIG__ ?? {}),
	nextcloudUrl: window.location.origin,
	openMode: window.__LIBRESIGN_CONFIG__?.openMode ?? 'new-tab',
}
