/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { FileAction, registerFileAction } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import { SIGN_STATUS } from '../domains/sign/enum.js'
import { fileStatus } from '../helpers/fileStatus.js'

let nodeIds = []
const nodeStatusById = new Map()

const action = new FileAction({
	id: 'show-status-inline',
	displayName: () => '',
	title: (nodes) => {
		const nodeId = nodes[0].fileid

		if (nodeStatusById.has(nodeId)) {
			const node = nodeStatusById.get(nodeId)

			if ([SIGN_STATUS.PARTIAL_SIGNED, SIGN_STATUS.SIGNED].includes(node.status)) {
				return node.original
					? t('libresign', 'original file')
					: fileStatus.find(status => status.id === node.status).label
			}
		}

		nodeIds.push(nodeId)

		return ''
	},
	exec: async () => null,
	iconSvgInline: (nodes) => {
		const nodeId = nodes[0].fileid

		if (nodeStatusById.has(nodeId)) {
			const node = nodeStatusById.get(nodeId)

			if ([SIGN_STATUS.PARTIAL_SIGNED, SIGN_STATUS.SIGNED].includes(node.status)) {
				return node.original
					? fileStatus.find(status => status.id === SIGN_STATUS.ABLE_TO_SIGN).icon
					: fileStatus.find(status => status.id === node.status).icon
			}
		}

		return ''
	},
	inline: () => true,
	enabled: (nodes) => {
		return loadState('libresign', 'certificate_ok')
			&& nodes.length > 0 && nodes
				.map(node => node.mime)
				.every(mime => mime === 'application/pdf')
	},
	order: -1,
})

axios.get(generateOcsUrl('/apps/libresign/api/v1/file/list'), {
	params: {
		nodeIds,
	},
}).then(({ data }) => {
	data.ocs.data.data.forEach(node => {
		nodeStatusById.set(node.nodeId, { status: node.status, original: true })

		if (node.signedNodeId) {
			nodeStatusById.set(node.signedNodeId, { status: node.status, original: false })
		}
	})

	registerFileAction(action)
}).finally(() => { nodeIds = [] })
