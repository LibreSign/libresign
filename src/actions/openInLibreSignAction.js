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

	enabled({ nodes }) {
		const certificateOk = loadState('libresign', 'certificate_ok')
		const allPdf = nodes?.length > 0 && nodes.every(node => node.mime === 'application/pdf')
		return certificateOk && allPdf
	},

	async exec({ nodes }) {
		const node = nodes[0]
		await window.OCA.Files.Sidebar.open(node.path)
		window.OCA.Files.Sidebar.setActiveTab('libresign')
		return null
	},

	order: -1000,
})

registerFileAction(action)
