/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => {
	const mockRegisterFileAction = vi.fn()
	const mockGetSidebar = vi.fn()
	const mockLoadState = vi.fn()
	const capturedActionRef = { value: null }

	return {
		capturedActionRef,
		mockRegisterFileAction,
		mockGetSidebar,
		mockLoadState
	}
})

vi.mock('@nextcloud/files', () => ({
	FileAction: class {
		constructor(config) {
			Object.assign(this, config)
		}
	},
	registerFileAction: (actionInstance) => {
		mocks.capturedActionRef.value = actionInstance
		mocks.mockRegisterFileAction(actionInstance)
	},
	getSidebar: mocks.mockGetSidebar,
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args) => mocks.mockLoadState(...args),
}))

vi.mock('@nextcloud/l10n', () => ({
	t: (app, text) => text,
}))

vi.mock('../../constants.js', () => ({
	FILE_STATUS: {
		DRAFT: 0,
		SIGNED: 3,
	},
}))

vi.mock('../../utils/fileStatus.js', () => ({
	getStatusLabel: (status) => `Status ${status}`,
	getStatusSvgInline: (status) => `<svg>${status}</svg>`,
}))

describe('showStatusInlineAction', () => {
	let action

	beforeEach(async () => {
		vi.resetModules()
		mocks.capturedActionRef.value = null
		mocks.mockRegisterFileAction.mockClear()
		mocks.mockGetSidebar.mockClear()
		mocks.mockLoadState.mockClear()
		mocks.mockLoadState.mockReturnValue(true)
		await import('../../actions/showStatusInlineAction.js')
		action = mocks.capturedActionRef.value
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
			mocks.mockGetSidebar.mockReturnValue(mockSidebar)

			const node = { fileid: 123, name: 'test.pdf' }
			const result = await action.exec({ nodes: [node] })

			expect(mockSidebar.open).toHaveBeenCalledWith(node, 'libresign')
			expect(mockSidebar.setActiveTab).toHaveBeenCalledWith('libresign')
			expect(result).toBe(null)
		})
	})

	describe('enabled', () => {
		it('returns false when certificate is not ok', () => {
			mocks.mockLoadState.mockReturnValue(false)

			const result = action.enabled({
				nodes: [{
					mime: 'application/pdf',
					attributes: {
						'libresign-signature-status': 3,
					},
				}],
			})

			expect(result).toBe(false)
		})

		it('returns false when nodes do not have status', () => {
			mocks.mockLoadState.mockReturnValue(true)

			const result = action.enabled({
				nodes: [{
					mime: 'application/pdf',
					attributes: {},
				}],
			})

			expect(result).toBe(false)
		})

		it('returns true for PDF with status', () => {
			mocks.mockLoadState.mockReturnValue(true)

			const result = action.enabled({
				nodes: [{
					mime: 'application/pdf',
					attributes: {
						'libresign-signature-status': 3,
					},
				}],
			})

			expect(result).toBe(true)
		})

		it('returns true for folder with status', () => {
			mocks.mockLoadState.mockReturnValue(true)

			const result = action.enabled({
				nodes: [{
					type: 'folder',
					attributes: {
						'libresign-signature-status': 2,
					},
				}],
			})

			expect(result).toBe(true)
		})

		it('returns false for non-PDF/non-folder', () => {
			mocks.mockLoadState.mockReturnValue(true)

			const result = action.enabled({
				nodes: [{
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
			mocks.mockLoadState.mockReturnValue(true)

			const result = action.enabled({
				nodes: [
					{
						mime: 'application/pdf',
						attributes: {
							'libresign-signature-status': 3,
						},
					},
					{
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
			expect(mocks.mockRegisterFileAction).toHaveBeenCalled()
		})
	})
})

