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

import SvgIcon from '../../img/app-dark.svg?raw'
import SvgAccountCheck from '@mdi/svg/svg/account-check.svg?raw'

import { isAccountIdentificationEnabled } from '../services/selfSigning.ts'

/**
 * Prompts user for envelope name via dialog
 */
async function promptEnvelopeName() {
	const envelopeName = await spawnDialog(
		EditNameDialog,
		{
			title: t('libresign', 'Envelope name'),
			label: t('libresign', 'Enter a name for the envelope'),
			placeholder: t('libresign', 'Envelope name'),
		},
	)
	return envelopeName
}

export const action = {
	id: 'open-in-libresign',
	displayName: () => t('libresign', 'Open in LibreSign'),
	iconSvgInline: () => SvgIcon,

	enabled({ nodes }) {
		const getNodeMime = (node) => node?.mime || node?.mimetype || ''

		if (!loadState('libresign', 'certificate_ok', false)) {
			return false
		}

		if (!nodes?.length) {
			return false
		}

		if (nodes.length === 1 && nodes[0].type === 'folder') {
			return nodes[0].attributes?.['libresign-signature-status'] !== undefined
		}

		const allPdf = nodes.every(node => getNodeMime(node) === 'application/pdf')
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
		const getNodeFileId = (node) => node?.fileid ?? node?.id

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
		const temporaryEnvelopeId = -Date.now()

		window.OCA.Libresign.pendingEnvelope = {
			id: temporaryEnvelopeId,
			nodeType: 'envelope',
			name: envelopeName,
			settings: {
				path: envelopePath,
			},
			files: nodes.map(node => ({ fileId: getNodeFileId(node) })),
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
// A separate, contextual shortcut for an untouched PDF. It prepares a draft
// for the current account; the user must still request and authorize signing.
export const signMyselfAction = {
	id: 'sign-myself-libresign',
	displayName: () => t('libresign', 'Sign myself'),
	iconSvgInline: () => SvgAccountCheck,
	enabled({ nodes }) {
		if (nodes?.length !== 1 || (nodes[0].mime || nodes[0].mimetype) !== 'application/pdf') {
			return false
		}
		if (nodes[0].attributes?.['libresign-signature-status'] !== undefined) {
			return false
		}
		if (!loadState('libresign', 'certificate_ok', false)
			|| !loadState('libresign', 'can_request_sign', false)) {
			return false
		}
		const initialPolicies = loadState('libresign', 'effective_policies', { policies: {} })
		return isAccountIdentificationEnabled(initialPolicies?.policies?.identify_methods?.effectiveValue)
	},
	async exec({ nodes }) {
		const node = nodes[0]
		const nodeId = node?.fileid ?? node?.id
		if (nodeId === undefined || nodeId === null) {
			return null
		}
		window.OCA.Libresign.pendingSelfSignNodeId = nodeId
		try {
			const sidebar = getSidebar()
			await sidebar.open(node, 'libresign')
			sidebar.setActiveTab('libresign')
		} catch (error) {
			delete window.OCA.Libresign.pendingSelfSignNodeId
			throw error
		}
		return null
	},
	order: -999,
}

registerFileAction(signMyselfAction)
