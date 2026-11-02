/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs-v8'

describe('dialogs v8 integration', () => {
	it.each([
		['success', showSuccess],
		['error', showError],
	] as const)('renders and dismisses a %s toast', async (type, showToast) => {
		const message = `LibreSign ${type} feedback`
		const toast = showToast(message, { timeout: -1 })
		await nextTick()
		expect(document.body.textContent).toContain(message)
		toast.hideToast()
	})
})
