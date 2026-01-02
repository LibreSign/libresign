/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { registerFileAction, FileAction } from '@nextcloud/files'
import { getCapabilities } from '@nextcloud/capabilities'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import EditNameDialog from '../Components/Common/EditNameDialog.vue'

// eslint-disable-next-line import/no-unresolved
import SvgIcon from '../../img/app-dark.svg?raw'

/**
 * Prompts user for envelope name via dialog
 */
function promptEnvelopeName() {
	return new Promise((resolve) => {
		const propsData = {
			title: t('libresign', 'Envelope name'),
			label: t('libresign', 'Enter a name for the envelope'),
			placeholder: t('libresign', 'Envelope name'),
		}

		spawnDialog(
			{
				...EditNameDialog,
				mounted() {
					EditNameDialog.mounted?.call(this)
					this.$on('close', (value) => {
						resolve(value)
					})
				},
			},
			propsData,
		)
	})
}

export const action = new FileAction({
	id: 'open-in-libresign',
	displayName: () => t('libresign', 'Open in LibreSign'),
	iconSvgInline: () => SvgIcon,

	enabled(nodes) {
		return loadState('libresign', 'certificate_ok')
			&& nodes.length > 0 && nodes
			.map(node => node.mime)
			.every(mime => mime === 'application/pdf')
	},

	async exec(node) {
		try {
			await window.OCA.Files.Sidebar.open(node.path)
			OCA.Files.Sidebar.setActiveTab('libresign')
			return null
		} catch (error) {
			logger.error('Error while opening sidebar', { error })
			return false
		}
	},

	/**
	 * Multiple files: create envelope (if > 1) or delegate to exec (if = 1)
	 */
	async execBatch({ nodes }) {
		if (nodes.length === 1) {
			await this.exec({ nodes })
			return [null]
		}

		const envelopeName = await promptEnvelopeName()

		if (!envelopeName) {
			return new Array(nodes.length).fill(null)
		}

		return axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
			files: nodes.map(node => ({ fileId: node.fileid })),
			name: envelopeName,
		}).then((response) => {
			const envelopeData = response.data?.ocs?.data

			window.OCA.Libresign.pendingEnvelope = envelopeData

			window.OCA.Files.Sidebar.close()

			window.OCA.Files.Sidebar.setActiveTab('libresign')
			const firstNode = nodes[0]
			window.OCA.Files.Sidebar.open(firstNode.path)

			return new Array(nodes.length).fill(null)
		}).catch((error) => {
			console.error('[LibreSign] API error:', error)
			showError(error.response?.data?.ocs?.data?.message)
			return new Array(nodes.length).fill(null)
		})
	},

	order: -1000,
})

registerFileAction(action)
