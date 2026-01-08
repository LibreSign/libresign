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
		const status = fileStatus.find(status => status.id === node.attributes['libresign-signature-status'])

		if (!signedNodeId || node.fileid === signedNodeId) {
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
		const status = fileStatus.find(status => status.id === node.attributes['libresign-signature-status'])

		if (!signedNodeId || node.fileid === signedNodeId) {
			return status?.icon ?? ''
		}

		const neutralFile = fileStatus.find(status => status.id === SIGN_STATUS.DRAFT)
		return neutralFile?.icon ?? ''
	},
	inline: () => true,
	enabled: (nodes) => {
		const certificateOk = loadState('libresign', 'certificate_ok')
		const allHaveStatus = nodes?.every(node => node.attributes['libresign-signature-status'] !== undefined)

		if (!certificateOk || !allHaveStatus) {
			return false
		}

		const allPdfOrFolder = nodes?.length > 0 && nodes.every(node =>
			node.mime === 'application/pdf' || node.type === 'folder'
		)

		return allPdfOrFolder
	},
	order: -1,
})

registerFileAction(action)
