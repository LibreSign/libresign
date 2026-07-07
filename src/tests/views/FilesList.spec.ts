/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import { openFilesListSidebarForFile } from '../../utils/filesListSidebar.ts'

describe('FilesList - sidebar opening business rules', () => {
	it('opens the sign sidebar when the current user can sign the selected file', async () => {
		const detailedFile = {
			id: 42,
			status: 1,
			statusText: 'Ready to sign',
			signers: [{ me: true, sign_request_uuid: 'sign-request-uuid' }],
			visibleElements: [],
		}
		const filesStore = {
			selectFile: vi.fn(),
			fetchFileDetail: vi.fn().mockResolvedValue(detailedFile),
			canSign: vi.fn().mockReturnValue(true),
			canRequestSign: false,
		}
		const sidebarStore = {
			activeSignTab: vi.fn(),
			activeRequestSignatureTab: vi.fn(),
			setActiveTab: vi.fn(),
		}
		const signStore = {
			setFileToSign: vi.fn(),
		}

		await openFilesListSidebarForFile(42, {
			filesStore,
			sidebarStore,
			signStore,
		})

		expect(filesStore.selectFile).toHaveBeenCalledWith(42)
		expect(filesStore.fetchFileDetail).toHaveBeenCalledWith({
			fileId: 42,
			force: true,
		})
		expect(signStore.setFileToSign).toHaveBeenCalledWith(detailedFile)
		expect(sidebarStore.activeSignTab).toHaveBeenCalledTimes(1)
		expect(sidebarStore.activeRequestSignatureTab).not.toHaveBeenCalled()
	})

	it('falls back to the request sidebar when the selected file is not signable but the user can request signatures', async () => {
		const detailedFile = {
			id: 42,
			status: 3,
			statusText: 'Signed',
			signers: [],
			visibleElements: [],
		}
		const filesStore = {
			selectFile: vi.fn(),
			fetchFileDetail: vi.fn().mockResolvedValue(detailedFile),
			canSign: vi.fn().mockReturnValue(false),
			canRequestSign: true,
		}
		const sidebarStore = {
			activeSignTab: vi.fn(),
			activeRequestSignatureTab: vi.fn(),
			setActiveTab: vi.fn(),
		}
		const signStore = {
			setFileToSign: vi.fn(),
		}

		await openFilesListSidebarForFile(42, {
			filesStore,
			sidebarStore,
			signStore,
		})

		expect(signStore.setFileToSign).not.toHaveBeenCalled()
		expect(sidebarStore.activeSignTab).not.toHaveBeenCalled()
		expect(sidebarStore.activeRequestSignatureTab).toHaveBeenCalledTimes(1)
		expect(sidebarStore.setActiveTab).not.toHaveBeenCalled()
	})

	it('clears the sidebar when the selected file is not signable and the user cannot request signatures', async () => {
		const detailedFile = {
			id: 42,
			status: 3,
			statusText: 'Signed',
			signers: [],
			visibleElements: [],
		}
		const filesStore = {
			selectFile: vi.fn(),
			fetchFileDetail: vi.fn().mockResolvedValue(detailedFile),
			canSign: vi.fn().mockReturnValue(false),
			canRequestSign: false,
		}
		const sidebarStore = {
			activeSignTab: vi.fn(),
			activeRequestSignatureTab: vi.fn(),
			setActiveTab: vi.fn(),
		}
		const signStore = {
			setFileToSign: vi.fn(),
		}

		await openFilesListSidebarForFile(42, {
			filesStore,
			sidebarStore,
			signStore,
		})

		expect(signStore.setFileToSign).not.toHaveBeenCalled()
		expect(sidebarStore.activeSignTab).not.toHaveBeenCalled()
		expect(sidebarStore.activeRequestSignatureTab).not.toHaveBeenCalled()
		expect(sidebarStore.setActiveTab).toHaveBeenCalledTimes(1)
	})
})
