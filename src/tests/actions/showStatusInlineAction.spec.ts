/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi, afterEach } from 'vitest'
import type { MockedFunction } from 'vitest'

describe('showStatusInlineAction', () => {
	type FileNode = {
		fileid: number
		name?: string
		attributes?: Record<string, number>
		mime?: string
		type?: string
	}

	type FileAction = {
		id: string
		displayName: () => string
		inline: () => boolean
		order: number
		title: (context: { nodes: FileNode[] }) => string
		iconSvgInline: (context: { nodes: FileNode[] }) => string
		exec: (context: { nodes: FileNode[] }) => Promise<null> | null
		enabled: (context: { nodes: FileNode[] }) => boolean
	}

	let action: FileAction
	let capturedActionRef: { value: FileAction | null }
	let mockRegisterFileAction: MockedFunction<(actionInstance: FileAction) => void>
	let mockGetSidebar: MockedFunction<() => { open: (node: FileNode, tab: string) => void; setActiveTab: (tab: string) => void }>
	let mockLoadState: MockedFunction<(app: string, key: string, defaultValue?: boolean) => boolean>

	beforeEach(async () => {
		// Clean up global state
		delete (globalThis as typeof globalThis & { _nc_files_scope?: unknown })._nc_files_scope

		// Create fresh mocks for this test
		capturedActionRef = { value: null }
		mockRegisterFileAction = vi.fn()
		mockGetSidebar = vi.fn()
		mockLoadState = vi.fn(() => true)

		// Setup mocks with fresh state
		vi.doMock('@nextcloud/files', () => ({
			FileAction: class {
				constructor(config: FileAction) {
					Object.assign(this, config)
				}
			},
			registerFileAction: (actionInstance: FileAction) => {
				capturedActionRef.value = actionInstance
				mockRegisterFileAction(actionInstance)
			},
			getSidebar: mockGetSidebar,
		}))

		vi.doMock('@nextcloud/initial-state', () => ({
			loadState: (...args: Parameters<typeof mockLoadState>) => mockLoadState(...args),
		}))

		vi.doMock('../../constants.js', () => ({
			FILE_STATUS: {
				DRAFT: 0,
				SIGNED: 3,
			},
		}))

		vi.doMock('../../utils/fileStatus.js', () => ({
			getStatusLabel: (status: number) => `Status ${status}`,
			getStatusSvgInline: (status: number) => `<svg>${status}</svg>`,
		}))

		// Reset modules and import the action
		vi.resetModules()
		await import('../../actions/showStatusInlineAction.js')

		// Capture the registered action
		if (!capturedActionRef.value) {
			throw new Error('Action was not registered')
		}
		action = capturedActionRef.value
	})

	afterEach(() => {
		// Clean up all mocks after each test
		vi.unmock('@nextcloud/files')
		vi.unmock('@nextcloud/initial-state')
		vi.unmock('@nextcloud/l10n')
		vi.unmock('../../constants.js')
		vi.unmock('../../utils/fileStatus.js')
	})

	it('has correct id', () => {
		expect(action.id).toBe('show-status-inline')
	})

	it('has empty display name', () => {
		expect(action.displayName()).toBe('')
	})

	it('is inline action', () => {
		expect(action.inline()).toBe(true)
	})

	it('has correct order', () => {
		expect(action.order).toBe(-1)
	})

	describe('title', () => {
		it('returns empty string when no nodes', () => {
			const result = action.title({ nodes: [] })
			expect(result).toBe('')
		})

		it('returns status label for signed node', () => {
			const result = action.title({
				nodes: [{
					fileid: 123,
					attributes: {
						'libresign-signed-node-id': 123,
						'libresign-signature-status': 3,
					},
				}],
			})
			expect(result).toBe('Status 3')
		})

		it('returns status label when no signed node id', () => {
			const result = action.title({
				nodes: [{
					fileid: 123,
					attributes: {
						'libresign-signature-status': 2,
					},
				}],
			})
			expect(result).toBe('Status 2')
		})

		it('returns "original file" for draft with different signed node id', () => {
			const result = action.title({
				nodes: [{
					fileid: 123,
					attributes: {
						'libresign-signed-node-id': 456,
						'libresign-signature-status': 0,
					},
				}],
			})
			expect(result).toBe('original file')
		})
	})

	describe('iconSvgInline', () => {
		it('returns empty string when no nodes', () => {
			const result = action.iconSvgInline({ nodes: [] })
			expect(result).toBe('')
		})

		it('returns status svg for signed node', () => {
			const result = action.iconSvgInline({
				nodes: [{
					fileid: 123,
					attributes: {
						'libresign-signed-node-id': 123,
						'libresign-signature-status': 3,
					},
				}],
			})
			expect(result).toBe('<svg>3</svg>')
		})

		it('returns status svg when no signed node id', () => {
			const result = action.iconSvgInline({
				nodes: [{
					fileid: 123,
					attributes: {
						'libresign-signature-status': 2,
					},
				}],
			})
			expect(result).toBe('<svg>2</svg>')
		})

		it('returns draft svg for original file with different signed node id', () => {
			const result = action.iconSvgInline({
				nodes: [{
					fileid: 123,
					attributes: {
						'libresign-signed-node-id': 456,
						'libresign-signature-status': 0,
					},
				}],
			})
			expect(result).toBe('<svg>0</svg>')
		})
	})

	describe('exec', () => {
		it('opens sidebar and sets active tab', async () => {
			const mockSidebar = {
				open: vi.fn(),
				setActiveTab: vi.fn(),
			}
			mockGetSidebar.mockReturnValue(mockSidebar)

			const node = { fileid: 123, name: 'test.pdf' }
			const result = await action.exec({ nodes: [node] })

			expect(mockSidebar.open).toHaveBeenCalledWith(node, 'libresign')
			expect(mockSidebar.setActiveTab).toHaveBeenCalledWith('libresign')
			expect(result).toBe(null)
		})
	})

	describe('enabled', () => {
		it('returns false when certificate is not ok', () => {
			mockLoadState.mockReturnValue(false)

			const result = action.enabled({
				nodes: [{
					fileid: 1,
					mime: 'application/pdf',
					attributes: {
						'libresign-signature-status': 3,
					},
				}],
			})

			expect(result).toBe(false)
		})

		it('returns false when nodes do not have status', () => {
			mockLoadState.mockReturnValue(true)

			const result = action.enabled({
				nodes: [{
					fileid: 1,
					mime: 'application/pdf',
					attributes: {},
				}],
			})

			expect(result).toBe(false)
		})

		it('returns true for PDF with status', () => {
			mockLoadState.mockReturnValue(true)

			const result = action.enabled({
				nodes: [{
					fileid: 1,
					mime: 'application/pdf',
					attributes: {
						'libresign-signature-status': 3,
					},
				}],
			})

			expect(result).toBe(true)
		})

		it('returns true for folder with status', () => {
			mockLoadState.mockReturnValue(true)

			const result = action.enabled({
				nodes: [{
					fileid: 1,
					type: 'folder',
					attributes: {
						'libresign-signature-status': 2,
					},
				}],
			})

			expect(result).toBe(true)
		})

		it('returns false for non-PDF/non-folder', () => {
			mockLoadState.mockReturnValue(true)

			const result = action.enabled({
				nodes: [{
					fileid: 1,
					mime: 'text/plain',
					type: 'file',
					attributes: {
						'libresign-signature-status': 3,
					},
				}],
			})

			expect(result).toBe(false)
		})

		it('returns true for multiple PDFs with status', () => {
			mockLoadState.mockReturnValue(true)

			const result = action.enabled({
				nodes: [
					{
						fileid: 1,
						mime: 'application/pdf',
						attributes: {
							'libresign-signature-status': 3,
						},
					},
					{
						fileid: 2,
						mime: 'application/pdf',
						attributes: {
							'libresign-signature-status': 2,
						},
					},
				],
			})

			expect(result).toBe(true)
		})
	})

	describe('registration', () => {
		it('registers file action', () => {
			expect(mockRegisterFileAction).toHaveBeenCalled()
		})
	})
})

