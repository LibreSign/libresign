/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { RouteLocationNormalized } from 'vue-router'

/**
 * Check if route is external (shared signature flow)
 * @param {RouteLocationNormalized} to Destination route
 * @param {RouteLocationNormalized} from Source route
 */
export const isExternal = (to: RouteLocationNormalized, from: RouteLocationNormalized): boolean => {
	if (from.path === '/') {
		return to.path.startsWith('/p/')
	}
	return from.path.startsWith('/p/')
}
