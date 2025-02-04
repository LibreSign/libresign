/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { Option } from '@marionebl/option'

import { translate as t } from '@nextcloud/l10n'

/**
 * Return custom options details from ID
 *
 * @param {string} id identification of custom option
 */
export function selectCustonOption(id) {
	return Option.from(options.find(item => item.id === id))
}

/**
 * More informations: https://www.ietf.org/rfc/rfc5280.txt
 */
export const options = [
	{
		id: 'CN',
		label: t('libresign', 'Name (CN)'),
		max: 64,
		value: '',
		helperText: t('libresign', 'Name (CN)'),
	},
	{
		id: 'C',
		label: 'Country',
		min: 2,
		max: 2,
		value: '',
		helperText: t('libresign', 'Two-letter ISO 3166 country code'),
	},
	{
		id: 'ST',
		label: 'State',
		min: 1,
		max: 128,
		value: '',
		helperText: t('libresign', 'Full name of states or provinces'),
	},
	{
		id: 'L',
		label: 'Locality',
		min: 1,
		max: 128,
		value: '',
		helperText: t('libresign', 'Name of a locality or place, such as a city, county, or other geographic region'),
	},
	{
		id: 'O',
		label: 'Organization',
		min: 1,
		max: 64,
		value: '',
		helperText: t('libresign', 'Name of an organization'),
	},
	{
		id: 'OU',
		label: 'OrganizationalUnit',
		min: 1,
		max: 64,
		value: '',
		helperText: t('libresign', 'Name of an organizational unit'),
	},
]
