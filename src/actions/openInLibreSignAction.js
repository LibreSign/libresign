/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
