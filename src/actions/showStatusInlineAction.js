/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { registerFileAction, getSidebar } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import { FILE_STATUS } from '../constants.js'
import { getStatusLabel, getStatusSvgInline } from '../utils/fileStatus.js'

const action = {
	id: 'show-status-inline',
	displayName: () => '',
	title: ({ nodes }) => {
		const node = nodes?.[0]
		if (!node || !node.attributes) return ''

		const signedNodeId = node.attributes['libresign-signed-node-id']
		const statusCode = node.attributes['libresign-signature-status']

		if (!signedNodeId || node.fileid === signedNodeId) {
			return getStatusLabel(statusCode) || ''
		}

		return t('libresign', 'original file')
	},
	exec: async ({ nodes }) => {
		const sidebar = getSidebar()
		const node = nodes?.[0]
		if (!node) return null
		sidebar.open(node, 'libresign')
		sidebar.setActiveTab('libresign')
		return null
	},
	iconSvgInline: ({ nodes }) => {
		const node = nodes?.[0]
		if (!node || !node.attributes) return ''

		const signedNodeId = node.attributes['libresign-signed-node-id']
		const statusCode = node.attributes['libresign-signature-status']

		if (!signedNodeId || node.fileid === signedNodeId) {
			return getStatusSvgInline(statusCode) || ''
		}

		return getStatusSvgInline(FILE_STATUS.DRAFT) || ''
	},
	inline: () => true,
	enabled: ({ nodes }) => {
		const certificateOk = loadState('libresign', 'certificate_ok')
		const allHaveStatus = nodes?.every(node => node.attributes?.['libresign-signature-status'] !== undefined)

		if (!certificateOk || !allHaveStatus) {
			return false
		}

		const allPdfOrFolder = nodes?.length > 0 && nodes.every(node =>
			node.mime === 'application/pdf' || node.type === 'folder'
		)

		return allPdfOrFolder
	},
	order: -1,
}

registerFileAction(action)
