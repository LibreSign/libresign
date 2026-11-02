/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import { getFilePickerBuilder, showError, showSuccess, showWarning } from '@nextcloud/dialogs-v8'

describe('dialogs v8 integration', () => {
	it.each([
		['success', showSuccess, 'status'],
		['warning', showWarning, 'status'],
		['error', showError, 'alert'],
	] as const)('renders and dismisses a %s toast', async (type, showToast, role) => {
		const message = `LibreSign ${type} feedback`
		const toast = showToast(message, { timeout: -1 })
		await nextTick()
		const notification = [...document.querySelectorAll(`[role="${role}"]`)]
			.find(element => element.textContent?.includes(message))
		expect(notification).toBeDefined()
		toast.hideToast()
		await nextTick()
		expect(notification?.isConnected).toBe(false)
	})

	it('provides the file picker API used by LibreSign', () => {
		const picker = getFilePickerBuilder('Select your file')
			.setMultiSelect(false)
			.setMimeTypeFilter(['application/pdf'])
			.addButton({ label: 'Choose', callback: () => undefined })
			.build()
		expect(typeof picker.pick).toBe('function')
	})
})
