/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { addNewFileMenuEntry, Permission, getSidebar } from '@nextcloud/files'
import type { NewMenuEntry, IFolder, INode } from '@nextcloud/files'
import { registerDavProperty } from '@nextcloud/files/dav'
import { getClient, resultToNode } from '@nextcloud/files/dav'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { getUploader } from '@nextcloud/upload'
import type { Uploader } from '@nextcloud/upload'
import type { FileStat, ResponseDataDetailed } from 'webdav'

import logger from './logger'
import LibreSignLogoSvg from '../img/app-colored.svg?raw'
import LibreSignLogoDarkSvg from '../img/app-dark.svg?raw'
import { useIsDarkTheme } from './helpers/useIsDarkTheme'

// Extend NewMenuEntry to include uploadManager
interface ExtendedNewMenuEntry extends NewMenuEntry {
	uploadManager?: Uploader
}

interface UploadPayload {
	file: {
		name: string
	}
}

registerDavProperty('nc:libresign-signature-status', { nc: 'http://nextcloud.org/ns' })
registerDavProperty('nc:libresign-signed-node-id', { nc: 'http://nextcloud.org/ns' })

addNewFileMenuEntry({
	id: 'libresign-request',
	displayName: t('libresign', 'New signature request'),
	iconSvgInline: useIsDarkTheme() ? LibreSignLogoDarkSvg : LibreSignLogoSvg,
	uploadManager: getUploader(),
	order: 1,
	enabled(context: IFolder) {
		return (context.permissions & Permission.CREATE) !== 0
	},
	async handler(this: ExtendedNewMenuEntry, context: IFolder, content: INode[]) {
		const input = document.createElement('input')
		input.accept = 'application/pdf'
		input.type = 'file'
		input.onchange = async (ev) => {
			const file = (ev.target as HTMLInputElement).files?.[0]
			input.remove()
			if (!file) {
				return
			}

			const path = context.path + '/' + file.name

			await this.uploadManager?.upload(file.name, file)

			await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
				file: {
					path,
				},
				name: file.name,
			})

			// Fetch the complete node object from the Files API
			const client = getClient()
			const result = await client.stat(path, { details: true }) as ResponseDataDetailed<FileStat>
			const node = resultToNode(result.data)

			// Open sidebar with LibreSign tab
			const sidebar = getSidebar()
			sidebar.open(node, 'libresign')
			sidebar.setActiveTab('libresign')
		}

		input.click()
	},
} as ExtendedNewMenuEntry)
