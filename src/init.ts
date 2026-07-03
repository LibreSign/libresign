/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { addNewFileMenuEntry, Permission, getSidebar, getSidebarTabs } from '@nextcloud/files'
import type { IFolder, INode } from '@nextcloud/files'
import { registerDavProperty } from '@nextcloud/files/dav'
import { getClient, getDefaultPropfind, getRootPath, resultToNode } from '@nextcloud/files/dav'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import type { FileStat, ResponseDataDetailed } from 'webdav'

import './actions/openInLibreSignAction.js'
import './actions/showStatusInlineAction.js'
import LibreSignLogoSvg from '../img/app-colored.svg?raw'
import LibreSignLogoDarkSvg from '../img/app-dark.svg?raw'
import { useIsDarkTheme } from './helpers/useIsDarkTheme'

interface UploadPayload {
	file: {
		name: string
	}
}

const libresignSidebarTabId = 'libresign'

function getNormalizedUploadPath(contextPath: string, fileName: string): string {
	const normalizedContextPath = contextPath === '/'
		? ''
		: contextPath.replace(/\/$/, '')

	return `${normalizedContextPath}/${fileName}`
}

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
	async handler(context: IFolder, content: INode[]) {
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
			const client = getClient()

			await client.putFileContents(remotePath, await file.arrayBuffer(), {
				contentLength: file.size,
				overwrite: false,
			})

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
			}) as ResponseDataDetailed<FileStat>
			const node = resultToNode(result.data)
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
