/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { RouteRecordNameGeneric } from 'vue-router'

export type RouteLocationLike = {
	path: string
	name?: RouteRecordNameGeneric | null
}

/**
 * Check if route is external (shared signature flow)
 * @param {RouteLocationLike} to Destination route
 * @param {RouteLocationLike} from Source route
 */
export const isExternal = (to: RouteLocationLike, from: RouteLocationLike): boolean => {
	if (from.path === '/') {
		return to.path.startsWith('/p/')
	}
	return from.path.startsWith('/p/')
}
