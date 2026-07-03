/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, type APIRequestContext, type Page } from '@playwright/test'

import { getSmallValidPdfBuffer } from './pdf-fixtures'

type RegisteredEntry = {
	id?: string
}

type NextcloudFilesWindow = Window & {
	_nc_newfilemenu?: {
		_entries?: RegisteredEntry[]
		getEntries?: () => RegisteredEntry[]
	}
	_nc_fileactions?: RegisteredEntry[]
	_nc_files_scope?: {
		v4_0?: {
			newFileMenu?: {
				_entries?: RegisteredEntry[]
				getEntries?: () => RegisteredEntry[]
			}
			fileActions?: Map<string, RegisteredEntry> | RegisteredEntry[]
		}
	}
}

type OcsFileCreateResponse = {
	ocs?: {
		data?: {
			id?: number
		}
	}
}

const initializedFilesHomes = new Set<string>()

export async function ensureFilesHomeInitialized(
	request: APIRequestContext,
	userId = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
): Promise<void> {
	if (initializedFilesHomes.has(userId)) {
		return
	}

	const fileName = `libresign-home-init-${Date.now()}`
	const pdfBuffer = await getSmallValidPdfBuffer()
	const headers = {
		'OCS-ApiRequest': 'true',
		Accept: 'application/json',
	}

	const response = await request.post('./ocs/v2.php/apps/libresign/api/v1/file', {
		headers,
		multipart: {
			name: fileName,
			file: {
				name: `${fileName}.pdf`,
				mimeType: 'application/pdf',
				buffer: pdfBuffer,
			},
		},
		failOnStatusCode: false,
	})

	if (!response.ok()) {
		throw new Error(`Failed to initialize Files home for user "${userId}": ${response.status()} ${await response.text()}`)
	}

	initializedFilesHomes.add(userId)

	const body = await response.json() as OcsFileCreateResponse
	const fileId = body.ocs?.data?.id
	if (typeof fileId === 'number' && fileId > 0) {
		await request.delete(`./ocs/v2.php/apps/libresign/api/v1/file/file_id/${fileId}`, {
			headers,
			failOnStatusCode: false,
		})
	}
}

export async function waitForFilesNewMenuEntry(page: Page, entryId: string): Promise<void> {
	await expect.poll(async () => {
		return page.evaluate((expectedId) => {
			const nextcloudWindow = window as NextcloudFilesWindow
			const menu = nextcloudWindow._nc_newfilemenu
				?? nextcloudWindow._nc_files_scope?.v4_0?.newFileMenu
			const entries = Array.isArray(menu?._entries)
				? menu._entries
				: menu?.getEntries?.() ?? []

			return Array.isArray(entries)
				&& entries.some((entry) => entry?.id === expectedId)
		}, entryId)
	}, { timeout: 15000 }).toBe(true)
}

export async function waitForFilesAction(page: Page, actionId: string): Promise<void> {
	await expect.poll(async () => {
		return page.evaluate((expectedId) => {
			const nextcloudWindow = window as NextcloudFilesWindow
			const scopedActions = nextcloudWindow._nc_files_scope?.v4_0?.fileActions
			const registeredActions = Array.isArray(nextcloudWindow._nc_fileactions)
				? nextcloudWindow._nc_fileactions
				: scopedActions instanceof Map
					? Array.from(scopedActions.values())
					: Array.isArray(scopedActions)
						? scopedActions
						: []

			return registeredActions.some((entry) => entry?.id === expectedId)
		}, actionId)
	}, { timeout: 15000 }).toBe(true)
}

export async function uploadFileToFilesApp(
	page: Page,
	file: {
		name: string
		mimeType: string
		buffer: Buffer
	},
	userId = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
): Promise<void> {
	await ensureFilesHomeInitialized(page.request, userId)
	const uploadPickerInput = page.locator('[data-cy-upload-picker-input]').first()
	await expect(uploadPickerInput).toBeAttached({ timeout: 15000 })
	await uploadPickerInput.setInputFiles(file)
}
