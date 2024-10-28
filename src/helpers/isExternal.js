/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/**
 * @param {string} to To this
 * @param {string} from From this
 */
export const isExternal = (to, from) => {
	if (from.path === '/') {
		return to.path.startsWith('/p/')
	}
	return from.path.startsWith('/p/')
}
