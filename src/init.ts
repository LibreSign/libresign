/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { addNewFileMenuEntry, Permission, getSidebar } from '@nextcloud/files'
import type { NewMenuEntry, IFolder, INode } from '@nextcloud/files'
import { registerDavProperty } from '@nextcloud/files/dav'
import { n, t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { getUploader } from '@nextcloud/upload'
import type { Uploader } from '@nextcloud/upload'

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

			this.uploadManager?.addNotifier(async (upload: UploadPayload) => {
				const path = context.path + '/' + upload.file.name
				await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
					file: {
						path,
					},
					name: upload.file.name,
				})
					.then(async ({ data }) => {
						const sidebar = getSidebar()
					sidebar.open({ path } as INode, 'libresign')
						sidebar.setActiveTab('libresign')
					})
					.catch((error) => logger.error('Error uploading file:', error))
			})
			this.uploadManager
			?.upload(file.name, file)
				.catch((error) => logger.debug('Error while uploading', { error }))
		}

		input.click()
	},
} as ExtendedNewMenuEntry)
