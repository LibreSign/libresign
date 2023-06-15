import { Option } from '@marionebl/option'
import { translate as t } from '@nextcloud/l10n'

export function selectCustonOption(id) {
  return Option.from(options.find(item => item.id === id))
}

export const options = [
  {
		id: 'C',
		label: 'Country',
		min: 2,
		max: 2,
		value: '',
		minHelper: t('libresign', 'Two-letter ISO 3166 country code'),
		defaultHelper: t('libresign', 'Two-letter ISO 3166 country code'),
	},
	{
		id: 'ST',
		label: 'State',
		min: 1,
		value: '',
		defaultHelper: t('libresign', 'Full name of states or provinces'),
	},
	{
		id: 'L',
		label: 'Locality',
		min: 1,
		value: '',
		defaultHelper: t('libresign', 'Name of a locality or place, such as a city, county, or other geographic region'),
	},
	{
		id: 'O',
		label: 'Organization',
		min: 1,
		value: '',
		defaultHelper: t('libresign', 'Name of an organization'),
	},
	{
		id: 'OU',
		label: 'OrganizationalUnit',
		min: 1,
		value: '',
		defaultHelper: t('libresign', 'Name of an organizational unit'),
	},
]
