/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import SignatureSvg from '@mdi/svg/svg/signature.svg'

import { FileAction, registerFileAction } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'

const action = new FileAction({
	id: 'show-status-inline',
	displayName: () => '',
	title: () => 'Status',
	exec: async () => null,
	iconSvgInline: () => SignatureSvg,
	inline: () => true,
	enabled: (nodes) => {
		return loadState('libresign', 'certificate_ok')
			&& nodes.length > 0 && nodes
			.map(node => node.mime)
			.every(mime => mime === 'application/pdf')
	},
	order: -1,
})

registerFileAction(action)
