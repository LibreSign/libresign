/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { Option } from '@marionebl/option'

import { t } from '@nextcloud/l10n'

interface CertificationOption {
	id: string
	label: string
	max: number
	min?: number
	value: string
	helperText: string
}

/**
 * Return custom options details from ID
 *
 * @param {string} id identification of custom option
 */
export function selectCustonOption(id: string): Option<CertificationOption> {
	return Option.from(options.find(item => item.id === id))
}

/**
 * More informations: https://www.ietf.org/rfc/rfc5280.txt
 */
export const options: CertificationOption[] = [
	{
		id: 'CN',
		label: t('libresign', 'Common Name (CN)'),
		max: 64,
		value: '',
		helperText: t('libresign', 'Common Name (CN)'),
	},
	{
		id: 'C',
		// TRANSLATORS Certificate subject field label. "C" means the two-letter country code in an X.509 certificate subject.
		label: t('libresign', 'Country'),
		min: 2,
		max: 2,
		value: '',
		helperText: t('libresign', 'Two-letter ISO 3166 country code'),
	},
	{
		id: 'ST',
		// TRANSLATORS Certificate subject field label. "ST" means state or province name in an X.509 certificate subject.
		label: t('libresign', 'State'),
		min: 1,
		max: 128,
		value: '',
		helperText: t('libresign', 'Full name of states or provinces'),
	},
	{
		id: 'L',
		// TRANSLATORS Certificate subject field label. "L" means locality, usually a city or municipality, in an X.509 certificate subject.
		label: t('libresign', 'Locality'),
		min: 1,
		max: 128,
		value: '',
		helperText: t('libresign', 'Name of a locality or place, such as a city, county, or other geographic region'),
	},
	{
		id: 'O',
		// TRANSLATORS Certificate subject field label. "O" means organization name in an X.509 certificate subject.
		label: t('libresign', 'Organization'),
		min: 1,
		max: 64,
		value: '',
		helperText: t('libresign', 'Name of an organization'),
	},
	{
		id: 'OU',
		// TRANSLATORS Certificate subject field label. "OU" means organizational unit, such as a department or team, in an X.509 certificate subject.
		label: t('libresign', 'Organizational Unit'),
		min: 1,
		max: 64,
		value: '',
		helperText: t('libresign', 'Name of an organizational unit'),
	},
]
