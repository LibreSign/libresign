/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

type ClickableLocator = {
	click: (options?: { timeout?: number }) => Promise<unknown>
	isVisible?: (options?: { timeout?: number }) => Promise<boolean>
}

type LocatorFactory = {
	locator: (selector: string) => {
		filter: (options: { hasText: RegExp }) => {
			first: () => ClickableLocator
		}
	}
}

/**
 * Clicks a visible ARIA option with a bounded timeout, then falls back to vue-select's floating menu item.
 *
 * @param page The page-like object used to query floating dropdown options.
 * @param matchingOption The ARIA option locator found by role.
 * @param targetPattern Text pattern used to locate the fallback option.
 * @param options Optional timeout overrides for the bounded click attempts.
 * @param options.optionClickTimeout Timeout for each click attempt.
 * @param options.fallbackVisibilityTimeout Timeout for waiting on the fallback option.
 */
export async function clickVisibleOptionOrFallback(
	page: LocatorFactory,
	matchingOption: ClickableLocator,
	targetPattern: RegExp,
	options?: {
		optionClickTimeout?: number
		fallbackVisibilityTimeout?: number
	},
): Promise<boolean> {
	const optionClickTimeout = options?.optionClickTimeout ?? 1500
	const fallbackVisibilityTimeout = options?.fallbackVisibilityTimeout ?? 2000

	const clickedOption = await matchingOption
		.click({ timeout: optionClickTimeout })
		.then(() => true)
		.catch(() => false)
	if (clickedOption) {
		return true
	}

	const floatingOption = page
		.locator('ul[role="listbox"] li, .vs__dropdown-menu--floating li')
		.filter({ hasText: targetPattern })
		.first()

	if (!(await floatingOption.isVisible?.({ timeout: fallbackVisibilityTimeout }).catch(() => false))) {
		return false
	}

	return floatingOption
		.click({ timeout: optionClickTimeout })
		.then(() => true)
		.catch(() => false)
}
