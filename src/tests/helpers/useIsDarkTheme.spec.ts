/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from '@vue/reactivity'
import { useIsDarkTheme, useIsDarkThemeElement } from '../../helpers/useIsDarkTheme'

vi.mock('@vueuse/core', () => ({
	createSharedComposable: (fn: any) => fn,
	useMutationObserver: vi.fn(),
	usePreferredDark: () => ref(false),
}))

vi.mock('../../utils/isDarkTheme.js', () => ({
	checkIfDarkTheme: vi.fn(() => false),
}))

describe('useIsDarkTheme', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('returns a ref with dark theme status', () => {
		const isDark = useIsDarkTheme()
		expect(isDark.value).toBe(false)
	})

	it('useIsDarkThemeElement checks specific element', () => {
		const mockElement = document.createElement('div')
		const isDark = useIsDarkThemeElement(mockElement)
		expect(isDark.value).toBe(false)
	})

	it('defaults to document.body if no element provided', () => {
		const isDark = useIsDarkThemeElement()
		expect(isDark.value).toBe(false)
	})
})
