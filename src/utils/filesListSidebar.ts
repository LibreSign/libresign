/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

type FilesListSidebarFileId = number | string

type FilesListSidebarFile = {
	id?: FilesListSidebarFileId
	status?: number
	statusText?: string
	signers?: Array<{
		me?: boolean
		sign_request_uuid?: string | null
	}>
	visibleElements?: Array<Record<string, unknown>>
	[key: string]: unknown
}

type FilesListSidebarFilesStore<TFile extends FilesListSidebarFile> = {
	selectFile: (id: number) => void
	fetchFileDetail: (options: { fileId: number, force?: boolean }) => Promise<TFile | null>
	canSign: (file: TFile | null | undefined) => boolean
	canRequestSign?: boolean
}

type FilesListSidebarStore = {
	activeSignTab: () => void
	activeRequestSignatureTab: () => void
	hideSidebar?: () => void
	setActiveTab?: (id?: string | null) => void
}

type FilesListSignStore<TFile extends FilesListSidebarFile> = {
	setFileToSign: (file: TFile) => void
}

function clearSidebar(sidebarStore: FilesListSidebarStore): void {
	if (typeof sidebarStore.setActiveTab === 'function') {
		sidebarStore.setActiveTab()
		return
	}

	if (typeof sidebarStore.hideSidebar === 'function') {
		sidebarStore.hideSidebar()
	}
}

export async function openFilesListSidebarForFile<TFile extends FilesListSidebarFile>(
	fileId: FilesListSidebarFileId,
	options: {
		filesStore: FilesListSidebarFilesStore<TFile>
		sidebarStore: FilesListSidebarStore
		signStore: FilesListSignStore<TFile>
	},
): Promise<TFile | null> {
	const normalizedFileId = typeof fileId === 'number' ? fileId : Number(fileId)
	if (Number.isNaN(normalizedFileId)) {
		clearSidebar(options.sidebarStore)
		return null
	}

	const detailedFile = await options.filesStore.fetchFileDetail({ fileId: normalizedFileId, force: true })
	options.filesStore.selectFile(normalizedFileId)

	if (detailedFile && options.filesStore.canSign(detailedFile)) {
		options.signStore.setFileToSign(detailedFile)
		options.sidebarStore.activeSignTab()
		return detailedFile
	}

	if (detailedFile && options.filesStore.canRequestSign === true) {
		options.sidebarStore.activeRequestSignatureTab()
		return detailedFile
	}

	clearSidebar(options.sidebarStore)
	return detailedFile
}
