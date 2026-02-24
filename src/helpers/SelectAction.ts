/**
 * SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import type { RouteLocationLike } from './isExternal'

import { isExternal } from './isExternal'
import { ACTION_CODES, ACTION_CODE_TO_ROUTE } from './ActionMapping'

const redirectURL = loadState('libresign', 'redirect', 'Home')

export const selectAction = (
	action: number,
	to: RouteLocationLike,
	from: RouteLocationLike,
): string | null => {
	const isExternalRoute = isExternal(to, from)
	const external = isExternalRoute ? 'External' : ''

	if (action === ACTION_CODES.REDIRECT) {
		window.location.replace(redirectURL.toString())
		return null
	}

	if (action === ACTION_CODES.DO_NOTHING) {
		if (typeof to.name === 'string') {
			return to.name
		}
		return null
	}

	const route = ACTION_CODE_TO_ROUTE[action]

	if (!route) {
		if (loadState('libresign', 'error', false)) {
			return 'DefaultPageError' + external
		}
		return null
	}

	if (route === 'redirect') {
		return null
	}

	return route + external
}
