/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

const startOfToday = () => (new Date()).setHours(0, 0, 0, 0)
const endOfToday = () => (new Date()).setHours(23, 59, 59, 999)

/**
 * Returns the list of time preset definitions (id, label, start, end).
 * Called as a function so that dates are always computed at the moment of use.
 *
 * @return {Array<{id: string, label: string, start: number, end: number}>}
 */
export function getTimePresets() {
	const today = startOfToday()
	const todayEnd = endOfToday()

	return [
		{
			id: 'today',
			label: t('libresign', 'Today'),
			start: today,
			end: todayEnd,
		},
		{
			id: 'last-7',
			label: t('libresign', 'Last 7 days'),
			start: today - (7 * 24 * 60 * 60 * 1000),
			end: todayEnd,
		},
		{
			id: 'last-30',
			label: t('libresign', 'Last 30 days'),
			start: today - (30 * 24 * 60 * 60 * 1000),
			end: todayEnd,
		},
		{
			id: 'this-year',
			label: t('libresign', 'This year ({year})', { year: (new Date()).getFullYear() }),
			start: new Date(today).setMonth(0, 1),
			end: todayEnd,
		},
		{
			id: 'last-year',
			label: t('libresign', 'Last year ({year})', { year: (new Date()).getFullYear() - 1 }),
			start: new Date(today).setFullYear(new Date().getFullYear() - 1, 0, 1),
			end: new Date(today).setMonth(0, 1),
		},
	]
}

/**
 * Returns the { start, end } range in milliseconds for the given preset id,
 * or null if the id is not recognised.
 *
 * @param {string} presetId
 * @return {{ start: number, end: number } | null}
 */
export function getTimePresetRange(presetId) {
	if (!presetId) {
		return null
	}
	const preset = getTimePresets().find(p => p.id === presetId)
	return preset ? { start: preset.start, end: preset.end } : null
}
