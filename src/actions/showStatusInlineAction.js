/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { FileAction, registerFileAction, getSidebar } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import { SIGN_STATUS } from '../domains/sign/enum.js'
import { fileStatus } from '../helpers/fileStatus.js'

const action = new FileAction({
	id: 'show-status-inline',
	displayName: () => '',
	title: ({ nodes }) => {
		const node = nodes?.[0]
		if (!node) return ''

		const signedNodeId = node.attributes['libresign-signed-node-id']

		if (!signedNodeId || node.fileid === signedNodeId) {
			const status = fileStatus.find(status => status.id === node.attributes['libresign-signature-status'])
			return status?.label ?? ''
		}
		return t('libresign', 'original file')
	},
	exec: async ({ nodes }) => {
		const sidebar = getSidebar()
		const node = nodes[0]
		sidebar.open(node, 'libresign')
		sidebar.setActiveTab('libresign')
		return null
	},
	iconSvgInline: ({ nodes }) => {
		const node = nodes?.[0]
		if (!node) return ''

		const signedNodeId = node.attributes['libresign-signed-node-id']

		if (!signedNodeId || node.fileid === signedNodeId) {
			const status = fileStatus.find(status => status.id === node.attributes['libresign-signature-status'])
			return status?.icon ?? ''
		}
		const ableToSignStatus = fileStatus.find(status => status.id === SIGN_STATUS.ABLE_TO_SIGN)
		return ableToSignStatus?.icon ?? ''
	},
	inline: () => true,
	enabled: ({ nodes }) => {
		const certificateOk = loadState('libresign', 'certificate_ok')
		const allPdf = nodes?.length > 0 && nodes.every(node => node.mime === 'application/pdf')
		const allHaveStatus = nodes?.every(node => node.attributes['libresign-signature-status'])

		return certificateOk && allPdf && allHaveStatus
	},
	order: -1,
})

registerFileAction(action)
