/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import { clickVisibleOptionOrFallback } from '../../../playwright/support/select-option'

describe('clickVisibleOptionOrFallback', () => {
	it('falls back to the floating vue-select option when the visible ARIA option click times out', async () => {
		const optionClick = vi.fn().mockRejectedValue(new Error('option click timed out'))
		const fallbackClick = vi.fn().mockResolvedValue(undefined)
		const fallbackIsVisible = vi.fn().mockResolvedValue(true)
		const firstFallback = {
			click: fallbackClick,
			isVisible: fallbackIsVisible,
		}
		const fallbackLocator = {
			filter: vi.fn(() => ({
				first: vi.fn(() => firstFallback),
			})),
		}
		const page = {
			locator: vi.fn(() => fallbackLocator),
		}
		const matchingOption = {
			click: optionClick,
		}

		const clicked = await clickVisibleOptionOrFallback(
			page,
			matchingOption,
			/libresign-footer-ui-flow-group/i,
			{ optionClickTimeout: 25, fallbackVisibilityTimeout: 25 },
		)

		expect(clicked).toBe(true)
		expect(optionClick).toHaveBeenCalledWith({ timeout: 25 })
		expect(page.locator).toHaveBeenCalledWith('ul[role="listbox"] li, .vs__dropdown-menu--floating li')
		expect(fallbackLocator.filter).toHaveBeenCalledWith({ hasText: /libresign-footer-ui-flow-group/i })
		expect(fallbackIsVisible).toHaveBeenCalledWith({ timeout: 25 })
		expect(fallbackClick).toHaveBeenCalledWith({ timeout: 25 })
	})

	it('returns false when neither the ARIA option nor the floating fallback can be clicked', async () => {
		const optionClick = vi.fn().mockRejectedValue(new Error('option click timed out'))
		const fallbackClick = vi.fn()
		const fallbackIsVisible = vi.fn().mockResolvedValue(false)
		const page = {
			locator: vi.fn(() => ({
				filter: vi.fn(() => ({
					first: vi.fn(() => ({
						click: fallbackClick,
						isVisible: fallbackIsVisible,
					})),
				})),
			})),
		}
		const matchingOption = {
			click: optionClick,
		}

		const clicked = await clickVisibleOptionOrFallback(
			page,
			matchingOption,
			/libresign-footer-ui-flow-group/i,
			{ optionClickTimeout: 25, fallbackVisibilityTimeout: 25 },
		)

		expect(clicked).toBe(false)
		expect(fallbackClick).not.toHaveBeenCalled()
	})
})
