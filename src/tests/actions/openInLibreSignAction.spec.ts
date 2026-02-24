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

interface FileActionConfig {
	id: string
	displayName: (files: unknown[], view: unknown) => string
	iconSvgInline: (files: unknown[], view: unknown) => string
	enabled?: (files: unknown[], view: unknown) => boolean
	exec: (file: unknown, view: unknown, dir: unknown) => Promise<unknown>
	execBatch?: (files: unknown[], view: unknown, dir: unknown) => Promise<unknown[]>
	order?: number
}

vi.mock('@nextcloud/files', () => ({
	FileAction: class FileAction {
		id: string
		displayName: FileActionConfig['displayName']
		iconSvgInline: FileActionConfig['iconSvgInline']
		enabled?: FileActionConfig['enabled']
		exec: FileActionConfig['exec']
		execBatch?: FileActionConfig['execBatch']
		order?: number

		constructor(config: FileActionConfig) {
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
	loadState: vi.fn((app, key, defaultValue) => {
		if (key === 'certificate_ok') return true
		return defaultValue
	}),
}))

vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((app: any, text: any) => text),
	translatePlural: vi.fn((app: any, singular: any, plural: any, count: any) => (count === 1 ? singular : plural)),
	t: vi.fn((app: any, text: any) => text),
	n: vi.fn((app: any, singular: any, plural: any, count: any) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('@nextcloud/vue/functions/dialog', () => ({
	spawnDialog: vi.fn((component: { mounted?: () => void }, props: unknown) => {
		return new Promise<void>((resolve) => {
			setTimeout(() => {
				if (component.mounted) {
					const instance = { $on: vi.fn() }
					component.mounted.call(instance)
					const call = instance.$on.mock.calls.find(
						(c) => c && c[0] === 'close'
					)
					const closeHandler = call ? call[1] : null
					if (closeHandler) {
						closeHandler('Test Envelope')
					}
				}
				resolve()
			}, 0)
		})
	}),
}))

describe('openInLibreSignAction rules', () => {
	let action: any
	let loadState: any
	let getCapabilities: any

	beforeEach(async () => {
		vi.clearAllMocks()
		mockSidebar.open.mockClear()
		mockSidebar.setActiveTab.mockClear()

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

			const enabled = action.enabled({
				nodes: [{ type: 'file', mime: 'application/pdf' }],
			})

			expect(enabled).toBe(false)
		})

		it('enables action when certificate configured', () => {
			loadState.mockReturnValue(true)

			const enabled = action.enabled({
				nodes: [{ type: 'file', mime: 'application/pdf' }],
			})

			expect(enabled).toBe(true)
		})
	})

	describe('enabled rules - single file', () => {
		beforeEach(() => {
			loadState.mockReturnValue(true)
		})

		it('enables for single PDF file', () => {
			const enabled = action.enabled({
				nodes: [{ type: 'file', mime: 'application/pdf' }],
			})

			expect(enabled).toBe(true)
		})

		it('disables for single non-PDF file', () => {
			const enabled = action.enabled({
				nodes: [{ type: 'file', mime: 'image/png' }],
			})

			expect(enabled).toBe(false)
		})

		it('enables for folder with signature status', () => {
			const enabled = action.enabled({
				nodes: [{
					type: 'folder',
					attributes: { 'libresign-signature-status': 'signed' },
				}],
			})

			expect(enabled).toBe(true)
		})

		it('disables for folder without signature status', () => {
			const enabled = action.enabled({
				nodes: [{
					type: 'folder',
					attributes: {},
				}],
			})

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

			const enabled = action.enabled({
				nodes: [
					{ type: 'file', mime: 'application/pdf' },
					{ type: 'file', mime: 'application/pdf' },
				],
			})

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

			const enabled = action.enabled({
				nodes: [
					{ type: 'file', mime: 'application/pdf' },
					{ type: 'file', mime: 'application/pdf' },
				],
			})

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

			const enabled = action.enabled({
				nodes: [
					{ type: 'file', mime: 'application/pdf' },
					{ type: 'file', mime: 'image/png' },
				],
			})

			expect(enabled).toBe(false)
		})
	})

	describe('enabled rules - nodes validation', () => {
		beforeEach(() => {
			loadState.mockReturnValue(true)
		})

		it('disables when nodes array is empty', () => {
			const enabled = action.enabled({ nodes: [] })
			expect(enabled).toBe(false)
		})

		it('disables when nodes is null', () => {
			const enabled = action.enabled({ nodes: null })
			expect(enabled).toBe(false)
		})

		it('disables when nodes is undefined', () => {
			const enabled = action.enabled({ nodes: undefined })
			expect(enabled).toBe(false)
		})
	})

	describe('exec execution for single file', () => {
		beforeEach(() => {
			loadState.mockReturnValue(true)
		})

		it('opens sidebar with file', async () => {
			const node = { type: 'file', mime: 'application/pdf', fileid: 123 }

			await action.exec({ nodes: [node] })

			expect(mockSidebar.open).toHaveBeenCalledWith(node, 'libresign')
			expect(mockSidebar.setActiveTab).toHaveBeenCalledWith('libresign')
		})

		it('opens sidebar with folder', async () => {
			const node = {
				type: 'folder',
				attributes: { 'libresign-signature-status': 'signed' },
				fileid: 456,
			}

			await action.exec({ nodes: [node] })

			expect(mockSidebar.open).toHaveBeenCalledWith(node, 'libresign')
		})

		it('returns null after execution', async () => {
			const node = { type: 'file', mime: 'application/pdf' }
			const result = await action.exec({ nodes: [node] })

			expect(result).toBeNull()
		})
	})

	describe('execBatch execution for multiple files', () => {
let spawnDialog: typeof import('@nextcloud/vue/functions/dialog').spawnDialog

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
			// @ts-expect-error - Mock implementation is compatible but type inference is too strict
			vi.mocked(spawnDialog).mockImplementationOnce((component: { mounted?: () => void }) => {
				return new Promise<void>((resolve) => {
					setTimeout(() => {
						if (component.mounted) {
							const instance = { $on: vi.fn() }
							component.mounted.call(instance)
							const call = instance.$on.mock.calls.find(
								(c) => c && c[0] === 'close'
							)
							const closeHandler = call ? call[1] : null
							if (closeHandler) {
								closeHandler(undefined)
							}
						}
						resolve()
					}, 0)
				})
			})

			const nodes = [
				{ type: 'file', mime: 'application/pdf', fileid: 1, dirname: '/Docs' },
				{ type: 'file', mime: 'application/pdf', fileid: 2, dirname: '/Docs' },
			]

			const result = await action.execBatch({ nodes })

			expect(result).toEqual([null, null])
		})

		it('opens sidebar after envelope creation', async () => {
			const nodes = [
				{ type: 'file', mime: 'application/pdf', fileid: 1, dirname: '/Docs' },
				{ type: 'file', mime: 'application/pdf', fileid: 2, dirname: '/Docs' },
			]

			window.OCA = { Libresign: {} }

			await action.execBatch({ nodes })

			expect(mockSidebar.open).toHaveBeenCalled()
			expect(mockSidebar.setActiveTab).toHaveBeenCalledWith('libresign')
		})

		it('creates correct pending envelope structure', async () => {
			const nodes = [
				{ type: 'file', mime: 'application/pdf', fileid: 1, dirname: '/Docs' },
				{ type: 'file', mime: 'application/pdf', fileid: 2, dirname: '/Docs' },
			]

			window.OCA = { Libresign: {} }

			await action.execBatch({ nodes })

			const pending = window.OCA.Libresign.pendingEnvelope
			expect(pending).toBeDefined()
			expect(pending.nodeType).toBe('envelope')
			expect(pending.files).toHaveLength(2)
			expect(pending.filesCount).toBe(2)
			expect(pending.signers).toEqual([])
		})

		it('calculates envelope path correctly with subdirectory', async () => {
			const nodes = [
				{ type: 'file', mime: 'application/pdf', fileid: 1, dirname: '/Documents/Contracts' },
				{ type: 'file', mime: 'application/pdf', fileid: 2, dirname: '/Documents/Contracts' },
			]

			window.OCA = { Libresign: {} }

			await action.execBatch({ nodes })

			const pending = window.OCA.Libresign.pendingEnvelope
			expect(pending.settings.path).toBe('/Documents/Contracts/Test Envelope')
		})

		it('calculates envelope path correctly in root', async () => {
			const nodes = [
				{ type: 'file', mime: 'application/pdf', fileid: 1, dirname: '/' },
				{ type: 'file', mime: 'application/pdf', fileid: 2, dirname: '/' },
			]

			window.OCA = { Libresign: {} }

			await action.execBatch({ nodes })

			const pending = window.OCA.Libresign.pendingEnvelope
			expect(pending.settings.path).toBe('/Test Envelope')
		})

		it('handles trailing slashes in dirname', async () => {
			const nodes = [
				{ type: 'file', mime: 'application/pdf', fileid: 1, dirname: '/Docs/' },
				{ type: 'file', mime: 'application/pdf', fileid: 2, dirname: '/Docs/' },
			]

			window.OCA = { Libresign: {} }

			await action.execBatch({ nodes })

			const pending = window.OCA.Libresign.pendingEnvelope
			expect(pending.settings.path).toBe('/Docs/Test Envelope')
		})

		it('preserves all file data in pending envelope', async () => {
			const nodes = [
				{
					type: 'file',
					mime: 'application/pdf',
					fileid: 123,
					dirname: '/Test',
					name: 'document.pdf',
				},
				{
					type: 'file',
					mime: 'application/pdf',
					fileid: 456,
					dirname: '/Test',
					name: 'contract.pdf',
				},
			]

			window.OCA = { Libresign: {} }

			await action.execBatch({ nodes })

			const pending = window.OCA.Libresign.pendingEnvelope
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
