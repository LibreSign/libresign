/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export const DEFAULT_USER_FOLDER = 'LibreSign'

export function normalizeDefaultUserFolder(value: EffectivePolicyValue): string {
	if (typeof value === 'string') {
		const normalized = value.trim()
		if (normalized !== '') {
			return normalized
		}
	}

	return DEFAULT_USER_FOLDER
}

export function isCustomDefaultUserFolder(value: EffectivePolicyValue): boolean {
	return normalizeDefaultUserFolder(value) !== DEFAULT_USER_FOLDER
}
