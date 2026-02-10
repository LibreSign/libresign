/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi, afterEach } from 'vitest'

// Mock @nextcloud/logger to avoid import-time errors with @nextcloud/vue
vi.mock('@nextcloud/logger', () => ({
	getLogger: vi.fn(() => ({
		error: vi.fn(),
		warn: vi.fn(),
		info: vi.fn(),
		debug: vi.fn(),
	})),
	getLoggerBuilder: vi.fn(() => ({
		setApp: vi.fn().mockReturnThis(),
		detectUser: vi.fn().mockReturnThis(),
		build: vi.fn(() => ({
			error: vi.fn(),
			warn: vi.fn(),
			info: vi.fn(),
			debug: vi.fn(),
		})),
	})),
}))

const mockSidebar = {
	open: vi.fn(),
	setActiveTab: vi.fn(),
}

const mockCapabilities = {
	libresign: {
		config: {
			envelope: { 'is-available': true },
		},
	},
}

vi.mock('@nextcloud/files', () => ({
	FileAction: class FileAction {
		constructor(config) {
			this.id = config.id
			this.displayName = config.displayName
			this.iconSvgInline = config.iconSvgInline
			this.enabled = config.enabled
			this.exec = config.exec
			this.execBatch = config.execBatch
			this.order = config.order
		}
	},
	registerFileAction: vi.fn(),
	getSidebar: vi.fn(() => mockSidebar),
}))

vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => mockCapabilities),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn(),
}))

vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn(key => key),
}))

vi.mock('@nextcloud/vue/functions/dialog', () => ({
	spawnDialog: vi.fn((component, props) => {
		return new Promise((resolve) => {
			setTimeout(() => {
				if (component.mounted) {
					const instance = { $on: vi.fn() }
					component.mounted.call(instance)
					const closeHandler = instance.$on.mock.calls.find(
						call => call[0] === 'close'
					)?.[1]
					if (closeHandler) closeHandler('Test Envelope')
				}
				resolve()
			}, 0)
		})
	}),
}))

describe('openInLibreSignAction rules', () => {
	let action
	let loadState
	let getCapabilities

	beforeEach(async () => {
		vi.clearAllMocks()
		vi.resetModules()
		mockSidebar.open.mockClear()
		mockSidebar.setActiveTab.mockClear()

		// Mock window.OCA.Files.Sidebar for Nextcloud 32
		if (!global.window) {
			global.window = {}
		}
		global.window.OCA = {
			Files: {
				Sidebar: {
					close: vi.fn(),
					open: mockSidebar.open,
					setActiveTab: mockSidebar.setActiveTab,
				},
			},
		}

		const { loadState: loadStateModule } = await import('@nextcloud/initial-state')
		const { getCapabilities: getCapabilitiesModule } = await import('@nextcloud/capabilities')

		loadState = loadStateModule
		getCapabilities = getCapabilitiesModule

		loadState.mockReturnValue(true)
		getCapabilities.mockReturnValue(mockCapabilities)

		const module = await import('../../actions/openInLibreSignAction.js')
		action = module.action
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	describe('enabled rules - certificate validation', () => {
		it('disables action when certificate not configured', () => {
			loadState.mockReturnValue(false)

			const enabled = action.enabled([{ type: 'file', mime: 'application/pdf' }])

			expect(enabled).toBe(false)
		})

		it('enables action when certificate configured', () => {
			loadState.mockReturnValue(true)

			const enabled = action.enabled([{ type: 'file', mime: 'application/pdf' }])

			expect(enabled).toBe(true)
		})
	})

	describe('enabled rules - single file', () => {
		beforeEach(() => {
			loadState.mockReturnValue(true)
		})

		it('enables for single PDF file', () => {
			const enabled = action.enabled([{ type: 'file', mime: 'application/pdf' }])

			expect(enabled).toBe(true)
		})

		it('disables for single non-PDF file', () => {
			const enabled = action.enabled([{ type: 'file', mime: 'image/png' }])

			expect(enabled).toBe(false)
		})

		it('enables for folder with signature status', () => {
			const enabled = action.enabled([{
				type: 'folder',
				attributes: { 'libresign-signature-status': 'signed' },
			}])

			expect(enabled).toBe(true)
		})

		it('disables for folder without signature status', () => {
			const enabled = action.enabled([{
				type: 'folder',
				attributes: {},
			}])

			expect(enabled).toBe(false)
		})
	})

	describe('enabled rules - multiple files', () => {
		beforeEach(() => {
			loadState.mockReturnValue(true)
		})

		it('enables for multiple PDFs when envelope available', () => {
			getCapabilities.mockReturnValue({
				libresign: {
					config: {
						envelope: { 'is-available': true },
					},
				},
			})

			const enabled = action.enabled([
				{ type: 'file', mime: 'application/pdf' },
				{ type: 'file', mime: 'application/pdf' },
			])

			expect(enabled).toBe(true)
		})

		it('disables for multiple PDFs when envelope not available', () => {
			getCapabilities.mockReturnValue({
				libresign: {
					config: {
						envelope: { 'is-available': false },
					},
				},
			})

			const enabled = action.enabled([
				{ type: 'file', mime: 'application/pdf' },
				{ type: 'file', mime: 'application/pdf' },
			])

			expect(enabled).toBe(false)
		})

		it('disables for mixed file types', () => {
			getCapabilities.mockReturnValue({
				libresign: {
					config: {
						envelope: { 'is-available': true },
					},
				},
			})

			const enabled = action.enabled([
				{ type: 'file', mime: 'application/pdf' },
				{ type: 'file', mime: 'image/png' },
			])

			expect(enabled).toBe(false)
		})
	})

	describe('enabled rules - nodes validation', () => {
		beforeEach(() => {
			loadState.mockReturnValue(true)
		})

		it('disables when nodes array is empty', () => {
			const enabled = action.enabled([])
			expect(enabled).toBe(false)
		})

		it('disables when nodes is null', () => {
			const enabled = action.enabled(null)
			expect(enabled).toBe(false)
		})

		it('disables when nodes is undefined', () => {
			const enabled = action.enabled(undefined)
			expect(enabled).toBe(false)
		})
	})

	describe('exec execution for single file', () => {
		beforeEach(() => {
			loadState.mockReturnValue(true)
		})

		it('opens sidebar with file', async () => {
			const node = { type: 'file', mime: 'application/pdf', fileid: 123, path: '/test.pdf' }

			await action.exec(node)

			expect(mockSidebar.open).toHaveBeenCalledWith('/test.pdf')
			expect(mockSidebar.setActiveTab).toHaveBeenCalledWith('libresign')
		})

		it('opens sidebar with folder', async () => {
			const node = {
				type: 'folder',
				attributes: { 'libresign-signature-status': 'signed' },
				fileid: 456,
				path: '/folder',
			}

			await action.exec(node)

			expect(mockSidebar.open).toHaveBeenCalledWith('/folder')
		})

		it('returns null after execution', async () => {
			const node = { type: 'file', mime: 'application/pdf', path: '/test.pdf' }
			const result = await action.exec(node)

			expect(result).toBeNull()
		})
	})

	describe('execBatch execution for multiple files', () => {
let spawnDialog

	beforeEach(async () => {
		loadState.mockReturnValue(true)
		getCapabilities.mockReturnValue({
			libresign: {
				config: {
					envelope: { 'is-available': true },
				},
			},
		})

		const dialogModule = await import('@nextcloud/vue/functions/dialog')
		spawnDialog = dialogModule.spawnDialog
	})

	it('returns null array when envelope name cancelled', async () => {
			vi.mocked(spawnDialog).mockImplementationOnce((component) => {
				if (component.mounted) {
					const instance = { $on: vi.fn() }
					component.mounted.call(instance)
					const closeHandler = instance.$on.mock.calls.find(
						call => call[0] === 'close'
					)?.[1]
					if (closeHandler) closeHandler(undefined)
				}
				return Promise.resolve()
			})

			const nodes = [
				{ type: 'file', mime: 'application/pdf', fileid: 1, path: '/Docs/file1.pdf', dirname: '/Docs' },
				{ type: 'file', mime: 'application/pdf', fileid: 2, path: '/Docs/file2.pdf', dirname: '/Docs' },
			]

			const result = await action.execBatch(nodes)

			expect(result).toEqual([null, null])
		})

		it('opens sidebar after envelope creation', async () => {
			const nodes = [
				{ type: 'file', mime: 'application/pdf', fileid: 1, path: '/Docs/file1.pdf', dirname: '/Docs' },
				{ type: 'file', mime: 'application/pdf', fileid: 2, path: '/Docs/file2.pdf', dirname: '/Docs' },
			]

			global.window.OCA.Libresign = {}

			await action.execBatch(nodes)

			expect(mockSidebar.open).toHaveBeenCalledWith('/Docs/file1.pdf')
			expect(mockSidebar.setActiveTab).toHaveBeenCalledWith('libresign')
		})

		it('creates correct pending envelope structure', async () => {
			const nodes = [
				{ type: 'file', mime: 'application/pdf', fileid: 1, path: '/Docs/file1.pdf', dirname: '/Docs' },
				{ type: 'file', mime: 'application/pdf', fileid: 2, path: '/Docs/file2.pdf', dirname: '/Docs' },
			]

			global.window.OCA.Libresign = {}

			await action.execBatch(nodes)

			const pending = global.window.OCA.Libresign.pendingEnvelope
			expect(pending).toBeDefined()
			expect(pending.nodeType).toBe('envelope')
			expect(pending.files).toHaveLength(2)
			expect(pending.filesCount).toBe(2)
			expect(pending.signers).toEqual([])
		})

		it('calculates envelope path correctly with subdirectory', async () => {
			const nodes = [
				{ type: 'file', mime: 'application/pdf', fileid: 1, path: '/Documents/Contracts/file1.pdf', dirname: '/Documents/Contracts' },
				{ type: 'file', mime: 'application/pdf', fileid: 2, path: '/Documents/Contracts/file2.pdf', dirname: '/Documents/Contracts' },
			]

			global.window.OCA.Libresign = {}

			await action.execBatch(nodes)

			const pending = global.window.OCA.Libresign.pendingEnvelope
			expect(pending.settings.path).toBe('/Documents/Contracts/Test Envelope')
		})

		it('calculates envelope path correctly in root', async () => {
			const nodes = [
				{ type: 'file', mime: 'application/pdf', fileid: 1, path: '/file1.pdf', dirname: '/' },
				{ type: 'file', mime: 'application/pdf', fileid: 2, path: '/file2.pdf', dirname: '/' },
			]

			global.window.OCA.Libresign = {}

			await action.execBatch(nodes)

			const pending = global.window.OCA.Libresign.pendingEnvelope
			expect(pending.settings.path).toBe('/Test Envelope')
		})

		it('handles trailing slashes in dirname', async () => {
			const nodes = [
				{ type: 'file', mime: 'application/pdf', fileid: 1, path: '/Docs/file1.pdf', dirname: '/Docs/' },
				{ type: 'file', mime: 'application/pdf', fileid: 2, path: '/Docs/file2.pdf', dirname: '/Docs/' },
			]

			global.window.OCA.Libresign = {}

			await action.execBatch(nodes)

			const pending = global.window.OCA.Libresign.pendingEnvelope
			expect(pending.settings.path).toBe('/Docs/Test Envelope')
		})

		it('preserves all file data in pending envelope', async () => {
			const nodes = [
				{
					type: 'file',
					mime: 'application/pdf',
					fileid: 123,
					path: '/Test/document.pdf',
					dirname: '/Test',
					name: 'document.pdf',
				},
				{
					type: 'file',
					mime: 'application/pdf',
					fileid: 456,
					path: '/Test/contract.pdf',
					dirname: '/Test',
					name: 'contract.pdf',
				},
			]

			global.window.OCA.Libresign = {}

			await action.execBatch(nodes)

			const pending = global.window.OCA.Libresign.pendingEnvelope
			expect(pending.files[0].fileId).toBe(123)
			expect(pending.files[1].fileId).toBe(456)
		})
	})

	describe('action properties', () => {
		it('has correct action id', () => {
			expect(action.id).toBe('open-in-libresign')
		})

		it('has negative order for higher priority', () => {
			expect(action.order).toBeLessThan(0)
		})

		it('has displayName function', () => {
			expect(typeof action.displayName).toBe('function')
		})

		it('has iconSvgInline function', () => {
			expect(typeof action.iconSvgInline).toBe('function')
		})
	})
})
