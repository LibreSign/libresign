/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'

import axios from '@nextcloud/axios'
import { addNewFileMenuEntry, Permission, registerDavProperty } from '@nextcloud/files'
import { translate, translatePlural } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { getUploader } from '@nextcloud/upload'

import logger from './logger.js'
import LibreSignLogoSvg from '../img/app-colored.svg?raw'
import LibreSignLogoDarkSvg from '../img/app-dark.svg?raw'
import { useIsDarkTheme } from './helpers/useIsDarkTheme.js'

Vue.prototype.t = translate
Vue.prototype.n = translatePlural
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

registerDavProperty('nc:signature-status', { nc: 'http://nextcloud.org/ns' })
registerDavProperty('nc:signed-node-id', { nc: 'http://nextcloud.org/ns' })

addNewFileMenuEntry({
	id: 'libresign-request',
	displayName: t('libresign', 'New signature request'),
	iconSvgInline: useIsDarkTheme() ? LibreSignLogoDarkSvg : LibreSignLogoSvg,
	uploadManager: getUploader(),
	order: 1,
	enabled() {
		return Permission.CREATE !== 0
	},
	async handler(context, content) {
		const input = document.createElement('input')
		input.accept = 'application/pdf'
		input.type = 'file'
		input.onchange = async (ev) => {
			const file = ev.target.files[0]
			input.remove()
			if (!file) {
				return
			}

			this.uploadManager.addNotifier(async (upload) => {
				const path = context.path + '/' + upload.file.name
				await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
					file: {
						path,
					},
					name: upload.file.name,
				})
					.then(async ({ data }) => {
						await window.OCA.Files.Sidebar.open(path)
						OCA.Files.Sidebar.setActiveTab('libresign')
					})
			})
			this.uploadManager
				.upload(file.name, file)
				.catch((error) => logger.debug('Error while uploading', { error }))
		}

		input.click()
	},
})
