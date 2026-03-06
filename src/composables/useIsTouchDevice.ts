/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed } from '@vue/reactivity'

export const useIsTouchDevice = () => {
	const isTouchDevice = computed(() => ('ontouchstart' in window) || (navigator.maxTouchPoints > 0))
	return {
		isTouchDevice,
	}
}
