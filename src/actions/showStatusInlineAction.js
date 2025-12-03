/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { FileAction, registerFileAction } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import { SIGN_STATUS } from '../domains/sign/enum.js'
import { fileStatus } from '../helpers/fileStatus.js'

const action = new FileAction({
	id: 'show-status-inline',
	displayName: () => '',
	title: (nodes) => {
		const node = nodes[0]

		const signedNodeId = node.attributes['libresign-signed-node-id']

		if (!signedNodeId || node.fileid === signedNodeId) {
			const status = fileStatus.find(status => status.id === node.attributes['libresign-signature-status'])
			return status?.label ?? ''
		}
		return t('libresign', 'original file')
	},
	exec: async (node) => {
		await window.OCA.Files.Sidebar.open(node.path)
		OCA.Files.Sidebar.setActiveTab('libresign')
		return null
	},
	iconSvgInline: (nodes) => {
		const node = nodes[0]

		const signedNodeId = node.attributes['libresign-signed-node-id']

		if (!signedNodeId || node.fileid === signedNodeId) {
			const status = fileStatus.find(status => status.id === node.attributes['libresign-signature-status'])
			return status?.icon ?? ''
		}
		const ableToSignStatus = fileStatus.find(status => status.id === SIGN_STATUS.ABLE_TO_SIGN)
		return ableToSignStatus?.icon ?? ''
	},
	inline: () => true,
	enabled: (nodes) => {
		return loadState('libresign', 'certificate_ok')
			&& nodes.length > 0
			&& nodes
			.map(node => node.mime)
			.every(mime => mime === 'application/pdf')
		&& nodes.every(node => node.attributes['libresign-signature-status'])
	},
	order: -1,
})

registerFileAction(action)
