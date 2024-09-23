/*
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
import { registerFileAction, FileAction } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'

// eslint-disable-next-line import/no-unresolved
import SvgIcon from '../../img/app-dark.svg?raw'
import logger from '../logger.js'

export const action = new FileAction({
	id: 'open-in-libresign',
	displayName: () => t('libresign', 'Open in LibreSign'),
	iconSvgInline: () => SvgIcon,

	enabled(nodes) {
		return loadState('libresign', 'certificate_ok')
			&& nodes.length > 0 && nodes
			.map(node => node.mime)
			.every(mime => mime === 'application/pdf')
	},

	async exec(node) {
		try {
			await window.OCA.Files.Sidebar.open(node.path)
			OCA.Files.Sidebar.setActiveTab('libresign')
			return null
		} catch (error) {
			logger.error('Error while opening sidebar', { error })
			return false
		}
	},

	order: -1000,
})

registerFileAction(action)
