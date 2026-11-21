/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export const DEFAULT_DRAW_INK_COLOR = '#000000'

/**
 * Normalize the draw ink color policy value to a lowercase #rrggbb string.
 *
 * @param value Raw effective policy value.
 * @return Normalized color or null when the input is not a hex color.
 */
export function resolveDrawInkColor(value: EffectivePolicyValue): string | null {
	if (typeof value !== 'string') {
		return null
	}

	const normalized = value.trim().toLowerCase()
	return /^#[0-9a-f]{6}$/.test(normalized) ? normalized : null
}
