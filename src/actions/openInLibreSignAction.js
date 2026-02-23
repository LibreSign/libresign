/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { registerFileAction, getSidebar } from '@nextcloud/files'
import { getCapabilities } from '@nextcloud/capabilities'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import EditNameDialog from '../components/Common/EditNameDialog.vue'

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

export const action = {
	id: 'open-in-libresign',
	displayName: () => t('libresign', 'Open in LibreSign'),
	iconSvgInline: () => SvgIcon,

	enabled({ nodes }) {
		if (!loadState('libresign', 'certificate_ok', false)) {
			return false
		}

		if (!nodes?.length) {
			return false
		}

		if (nodes.length === 1 && nodes[0].type === 'folder') {
			return nodes[0].attributes?.['libresign-signature-status'] !== undefined
		}

		const allPdf = nodes.every(node => node.mime === 'application/pdf')
		if (!allPdf) {
			return false
		}

		if (nodes.length > 1) {
			return getCapabilities()?.libresign?.config?.envelope?.['is-available'] === true
		}

		return true
	},

	/**
	 * Single file or folder: open in sidebar
	 */
	async exec({ nodes }) {
		const sidebar = getSidebar()
		const node = nodes[0]
		await sidebar.open(node, 'libresign')
		sidebar.setActiveTab('libresign')
		return null
	},

	/**
	 * Multiple files: prepare envelope data and delegate to sidebar
	 * Similar to exec, but passes multiple files to the sidebar for processing
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

		const rawDir = nodes[0].dirname ?? nodes[0].path.substring(0, nodes[0].path.lastIndexOf('/'))
		const normalizedDir = (rawDir && rawDir !== '/') ? rawDir.replace(/\/+$/, '') : ''
		const envelopePath = normalizedDir ? `${normalizedDir}/${envelopeName}` : `/${envelopeName}`

		window.OCA.Libresign.pendingEnvelope = {
			id: `envelope_${Date.now()}`,
			nodeType: 'envelope',
			name: envelopeName,
			settings: {
				path: envelopePath,
			},
			files: nodes.map(node => ({ fileId: node.fileid })),
			filesCount: nodes.length,
			signers: [],
			uuid: null,
		}

		const sidebar = getSidebar()
		const firstNode = nodes[0]
		await sidebar.open(firstNode, 'libresign')
		sidebar.setActiveTab('libresign')

		return new Array(nodes.length).fill(null)
	},

	order: -1000,
}

registerFileAction(action)
