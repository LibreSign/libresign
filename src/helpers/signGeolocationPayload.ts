/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { CollectedGeolocation } from './signerGeolocation'

export function withCollectedDeviceGeolocation<T extends object>(
	payload: T,
	geolocation: CollectedGeolocation | null | undefined,
): T & { deviceGeolocation?: CollectedGeolocation } {
	if (!geolocation) {
		return { ...payload }
	}

	return {
		...payload,
		deviceGeolocation: geolocation,
	}
}
