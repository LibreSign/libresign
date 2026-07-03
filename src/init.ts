/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { addNewFileMenuEntry, Permission, getSidebar, getSidebarTabs, type IFolder } from '@nextcloud/files'
import { getClient, getDefaultPropfind, getRootPath, registerDavProperty, resultToNode } from '@nextcloud/files/dav'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { getUploader } from '@nextcloud/upload'

import './actions/openInLibreSignAction.js'
import './actions/showStatusInlineAction.js'
import LibreSignLogoSvg from '../img/app-colored.svg?raw'
import LibreSignLogoDarkSvg from '../img/app-dark.svg?raw'
import { useIsDarkTheme } from './helpers/useIsDarkTheme'

const libresignSidebarTabId = 'libresign'

/**
 * Build the absolute user-tree path for the uploaded file.
 *
 * @param contextPath Current Files folder path.
 * @param fileName Uploaded file name.
 */
function getNormalizedUploadPath(contextPath: string, fileName: string): string {
	const normalizedContextPath = contextPath === '/'
		? ''
		: contextPath.replace(/\/$/, '')

	return `${normalizedContextPath}/${fileName}`
}

/**
 * Wait until the LibreSign Files sidebar tab is registered.
 *
 * @param tabId Sidebar tab identifier.
 * @param timeoutMs Maximum wait time in milliseconds.
 */
async function waitForSidebarTabRegistration(tabId: string, timeoutMs = 5000): Promise<void> {
	const startedAt = Date.now()
	while (Date.now() - startedAt < timeoutMs) {
		if (getSidebarTabs().some((tab) => tab.id === tabId)) {
			return
		}

		await new Promise((resolve) => setTimeout(resolve, 50))
	}
}

registerDavProperty('nc:libresign-signature-status', { nc: 'http://nextcloud.org/ns' })
registerDavProperty('nc:libresign-signed-node-id', { nc: 'http://nextcloud.org/ns' })

addNewFileMenuEntry({
	id: 'libresign-request',
	displayName: t('libresign', 'New signature request'),
	iconSvgInline: useIsDarkTheme() ? LibreSignLogoDarkSvg : LibreSignLogoSvg,
	order: 1,
	enabled(context: IFolder) {
		if (!loadState('libresign', 'certificate_ok', false)) {
			return false
		}
		return (context.permissions & Permission.CREATE) !== 0
	},
	async handler(context: IFolder) {
		const input = document.createElement('input')
		input.accept = 'application/pdf'
		input.type = 'file'
		input.onchange = async (ev) => {
			const file = (ev.target as HTMLInputElement).files?.[0]
			input.remove()
			if (!file) {
				return
			}

			const path = getNormalizedUploadPath(context.path, file.name)
			const remotePath = `${getRootPath()}${path}`
			await getUploader().upload(file.name, file, context.source)

			const client = getClient()

			await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
				file: {
					path,
				},
				name: file.name,
			})

			// Fetch the complete node object including Nextcloud-specific props (fileid, etc.)
			const result = await client.stat(remotePath, {
				details: true,
				data: getDefaultPropfind(),
			}) as { data: unknown }
			const node = resultToNode(result.data as Parameters<typeof resultToNode>[0])
			emit('files:node:created', node)

			// Open sidebar with LibreSign tab
			const sidebar = getSidebar()
			await sidebar.open(node, libresignSidebarTabId)
			await waitForSidebarTabRegistration(libresignSidebarTabId)
			sidebar.setActiveTab(libresignSidebarTabId)
		}

		input.click()
	},
})
