/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { FileAction, registerFileAction } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import { FILE_STATUS } from '../constants.js'
import { getStatusLabel, getStatusSvgInline } from '../utils/fileStatus.js'

const getNodeId = (node) => node?.fileid ?? node?.id
const getNodeMime = (node) => node?.mime || node?.mimetype || ''
const getNodes = input => Array.isArray(input) ? input : input?.nodes ?? []
const getNode = input => Array.isArray(input) ? input[0] : input?.nodes?.[0] ?? input

const action = new FileAction({
	id: 'show-status-inline',
	displayName: () => '',
	title: (input) => {
		const nodes = getNodes(input)
		const node = nodes?.[0]
		if (!node || !node.attributes) return ''

		const signedNodeId = node.attributes['libresign-signed-node-id']
		const statusCode = node.attributes['libresign-signature-status']

		if (!signedNodeId || getNodeId(node) === signedNodeId) {
			return getStatusLabel(statusCode) || ''
		}

		return t('libresign', 'original file')
	},
	exec: async (input) => {
		const node = getNode(input)
		if (!node) return null
		const sidebar = window.OCA.Files.Sidebar
		await sidebar.open(node.path)
		sidebar.setActiveTab('libresign')
		return null
	},
	iconSvgInline: (input) => {
		const nodes = getNodes(input)
		const node = nodes?.[0]
		if (!node || !node.attributes) return ''

		const signedNodeId = node.attributes['libresign-signed-node-id']
		const statusCode = node.attributes['libresign-signature-status']

		if (!signedNodeId || getNodeId(node) === signedNodeId) {
			return getStatusSvgInline(statusCode) || ''
		}

		return getStatusSvgInline(FILE_STATUS.DRAFT) || ''
	},
	inline: () => true,
	enabled: (input) => {
		const nodes = getNodes(input)
		const certificateOk = loadState('libresign', 'certificate_ok')
		const allHaveStatus = nodes?.every(node => node.attributes?.['libresign-signature-status'] !== undefined)

		if (!certificateOk || !allHaveStatus) {
			return false
		}

		const allPdfOrFolder = nodes?.length > 0 && nodes.every(node =>
			getNodeMime(node) === 'application/pdf' || node.type === 'folder'
		)

		return allPdfOrFolder
	},
	order: -1,
})

registerFileAction(action)
