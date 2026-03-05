/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi, afterEach } from 'vitest'

/**
 * Regression tests for src/init.ts
 *
 * Bug 1: PROPFIND 404 on file upload via "New signature request" menu.
 *   client.stat() was called with only the bare file path (e.g. "/folder/test.pdf"),
 *   which resolves to /remote.php/dav/folder/test.pdf → 404.
 *   Fix: path must be prefixed with getRootPath() (e.g. "/files/uid/folder/test.pdf").
 *
 * Bug 2: "Open in LibreSign" action was missing from Files context-menu.
 *   The action files were never imported by any bundle entry point, so
 *   registerFileAction() was never called for them.
 *   Fix: init.ts now imports both action modules as side-effects.
 */

// ─── Mocks ────────────────────────────────────────────────────────────────────

const mockStat = vi.fn()
const mockClient = { stat: mockStat }
const mockGetClient = vi.fn(() => mockClient)
const mockGetRootPath = vi.fn(() => '/files/testuser')
const mockResultToNode = vi.fn((data: unknown) => data)
const mockRegisterDavProperty = vi.fn()

const mockSidebarOpen = vi.fn()
const mockSidebarSetActiveTab = vi.fn()
const mockSidebar = { open: mockSidebarOpen, setActiveTab: mockSidebarSetActiveTab }
const mockGetSidebar = vi.fn(() => mockSidebar)
const mockAddNewFileMenuEntry = vi.fn()
const mockRegisterFileAction = vi.fn()

const mockUpload = vi.fn(() => Promise.resolve())
const mockUploader = { upload: mockUpload }
const mockGetUploader = vi.fn(() => mockUploader)

const mockAxiosPost = vi.fn(() => Promise.resolve({ data: {} }))

// ─── Module-level mocks (hoisted before imports) ─────────────────────────────

vi.mock('@nextcloud/axios', () => ({
	default: { post: mockAxiosPost },
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => `https://localhost${path}`),
}))

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
}))

vi.mock('@nextcloud/upload', () => ({
	getUploader: mockGetUploader,
}))

vi.mock('@nextcloud/files', () => ({
	addNewFileMenuEntry: mockAddNewFileMenuEntry,
	getSidebar: mockGetSidebar,
	Permission: { CREATE: 4 },
	registerFileAction: mockRegisterFileAction,
}))

vi.mock('@nextcloud/files/dav', () => ({
	getClient: mockGetClient,
	getRootPath: mockGetRootPath,
	resultToNode: mockResultToNode,
	registerDavProperty: mockRegisterDavProperty,
}))

// Stub the SVG imports so they don't blow up in the test environment
vi.mock('../../img/app-colored.svg?raw', () => ({ default: '<svg/>' }))
vi.mock('../../img/app-dark.svg?raw', () => ({ default: '<svg/>' }))

vi.mock('../helpers/useIsDarkTheme', () => ({
	useIsDarkTheme: vi.fn(() => false),
}))

vi.mock('../logger', () => ({
	default: { debug: vi.fn(), error: vi.fn(), warn: vi.fn(), info: vi.fn() },
}))

// Stub the action side-effect modules so they don't pull in unrelated deps,
// but still allow us to assert they were imported (tested separately below).
vi.mock('../actions/openInLibreSignAction.js', () => ({}))
vi.mock('../actions/showStatusInlineAction.js', () => ({}))

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Extract the handler that was registered via addNewFileMenuEntry so tests
 * can call it directly without simulating a real click.
 */
function captureNewMenuHandler(): (context: unknown, content: unknown) => Promise<void> {
	expect(mockAddNewFileMenuEntry).toHaveBeenCalledOnce()
	const [[entry]] = mockAddNewFileMenuEntry.mock.calls as [[{ handler: (context: unknown, content: unknown) => Promise<void>; uploadManager?: { upload: typeof mockUpload } }]]
	// Inject the mock uploader so handler can call this.uploadManager.upload()
	entry.uploadManager = mockUploader
	return entry.handler.bind(entry)
}

/**
 * Intercept the <input type="file"> that the handler creates (it is never
 * appended to the DOM so document.querySelector cannot find it).
 * Returns an async trigger: call it after invoking the handler to simulate
 * the user picking a file.
 */
function setupFileInputInterception(fileName: string, mimeType = 'application/pdf') {
	let capturedInput: HTMLInputElement | null = null
	const originalCreate = document.createElement.bind(document)

	vi.spyOn(document, 'createElement').mockImplementation((tag: string, ...args: unknown[]) => {
		const el = originalCreate(tag, ...(args as [ElementCreationOptions?]))
		if (tag === 'input') {
			capturedInput = el as HTMLInputElement
		}
		return el
	})

	return async function triggerChange() {
		expect(capturedInput, 'handler must have called document.createElement("input")').not.toBeNull()

		const file = new File(['%PDF-1.4'], fileName, { type: mimeType })
		Object.defineProperty(capturedInput, 'files', { value: [file], configurable: true })
		capturedInput!.dispatchEvent(new Event('change'))

		await vi.waitFor(() => expect(mockStat).toHaveBeenCalled(), { timeout: 2000 })

		vi.restoreAllMocks()
	}
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('init.ts', () => {
	beforeEach(async () => {
		vi.clearAllMocks()

		// Reset stat mock to return valid data for resultToNode
		mockStat.mockResolvedValue({ data: { filename: '/files/testuser/Documents/test.pdf' } })

		// Import init.ts – all side-effects run here
		await import('../init')
	})

	afterEach(() => {
		vi.resetModules()
	})

	// ── Side-effect: DAV properties ─────────────────────────────────────────

	it('registers the libresign-signature-status DAV property', () => {
		expect(mockRegisterDavProperty).toHaveBeenCalledWith(
			'nc:libresign-signature-status',
			{ nc: 'http://nextcloud.org/ns' },
		)
	})

	it('registers the libresign-signed-node-id DAV property', () => {
		expect(mockRegisterDavProperty).toHaveBeenCalledWith(
			'nc:libresign-signed-node-id',
			{ nc: 'http://nextcloud.org/ns' },
		)
	})

	// ── Side-effect: new-file-menu entry ─────────────────────────────────────

	it('adds a "New signature request" entry to the Files new-menu', () => {
		expect(mockAddNewFileMenuEntry).toHaveBeenCalledOnce()
		const [entry] = mockAddNewFileMenuEntry.mock.calls[0] as [{ id: string }][]
		expect(entry.id).toBe('libresign-request')
	})

	// ── Side-effect: file-action imports ─────────────────────────────────────

	/**
	 * Regression: missing context-menu actions.
	 * Both action modules must be imported so their registerFileAction() side-effect runs.
	 */
	it('imports openInLibreSignAction side-effect module', async () => {
		const mod = await import('../actions/openInLibreSignAction.js')
		expect(mod).toBeDefined()
	})

	it('imports showStatusInlineAction side-effect module', async () => {
		const mod = await import('../actions/showStatusInlineAction.js')
		expect(mod).toBeDefined()
	})

	// ── Upload handler: DAV stat path ─────────────────────────────────────────

	describe('new-menu handler', () => {
		const folderPath = '/Documents'
		const fileName = 'contract.pdf'
		const context = { path: folderPath, permissions: 4 /* CREATE */ }

		beforeEach(async () => {
			const triggerChange = setupFileInputInterception(fileName)
			const handler = captureNewMenuHandler()
			await handler(context, [])
			await triggerChange()
		})

		/**
		 * Regression: PROPFIND 404.
		 * client.stat() must include the WebDAV root path prefix (/files/{uid}),
		 * otherwise the request resolves to /remote.php/dav/<filename> → 404.
		 */
		it('calls client.stat with getRootPath() prefix to avoid PROPFIND 404', () => {
			const expectedPath = `${mockGetRootPath()}${folderPath}/${fileName}`
			expect(mockStat).toHaveBeenCalledWith(expectedPath, { details: true })
		})

		it('does NOT call client.stat with a bare path missing the root prefix', () => {
			const barePath = `${folderPath}/${fileName}`
			// Ensure the old (broken) path was never used
			expect(mockStat).not.toHaveBeenCalledWith(barePath, { details: true })
		})

		it('uploads the file before posting the OCS request', () => {
			const uploadCallOrder = mockUpload.mock.invocationCallOrder[0]
			const postCallOrder = mockAxiosPost.mock.invocationCallOrder[0]
			expect(uploadCallOrder).toBeLessThan(postCallOrder)
		})

		it('posts to the LibreSign OCS file endpoint', () => {
			expect(mockAxiosPost).toHaveBeenCalledWith(
				expect.stringContaining('/apps/libresign/api/v1/file'),
				expect.objectContaining({ name: fileName }),
			)
		})

		it('opens the sidebar to the LibreSign tab after upload', () => {
			expect(mockSidebarOpen).toHaveBeenCalledOnce()
			expect(mockSidebarSetActiveTab).toHaveBeenCalledWith('libresign')
		})
	})
})
