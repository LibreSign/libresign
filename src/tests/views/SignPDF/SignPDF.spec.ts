/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { mount } from '@vue/test-utils'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path) => `/ocs/v2.php/apps/libresign${path}`),
	generateUrl: vi.fn((path) => `/apps/libresign${path}`),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
	},
}))

vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => ({})),
}))

vi.mock('../../../components/PdfEditor/PdfEditor.vue', () => ({
	default: {
		name: 'PdfEditor',
		template: '<div data-test="pdf-editor" />',
	},
}))

vi.mock('../../../store/files.js', () => {
	const filesInstance = {
		fetchFileDetail: vi.fn(),
		addFile: vi.fn(),
		selectFile: vi.fn(),
		getFile: vi.fn(),
	}
	return {
		useFilesStore: vi.fn(() => filesInstance),
	}
})

vi.mock('../../../store/sidebar.js', () => {
	const sidebarInstance = {
		toggleSidebar: vi.fn(),
		hideSidebar: vi.fn(),
		activeSignTab: vi.fn(),
	}
	return {
		useSidebarStore: vi.fn(() => sidebarInstance),
	}
})

vi.mock('../../../store/sign.js', async () => {
	const actual = await vi.importActual('../../../store/sign.js')
	return actual
})

describe('SignPDF.vue', () => {
	const createSignDocument = (overrides = {}) => ({
		id: 1,
		name: 'Envelope',
		description: '',
		status: 0,
		statusText: '',
		url: '/apps/libresign/p/pdf/uuid-123',
		nodeId: 1,
		nodeType: 'file' as const,
		uuid: 'uuid-123',
		signers: [],
		visibleElements: [],
		...overrides,
	})

	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	it('attaches envelope files to signStore document', async () => {
		const SignPDF = (await import('../../../views/SignPDF/SignPDF.vue')).default
		const { loadState } = await import('@nextcloud/initial-state')
		const { useSignStore } = await import('../../../store/sign.js')
		const signStore = useSignStore()

		signStore.document = createSignDocument({
			nodeType: 'envelope',
			files: [],
		})

		const wrapper = mount(SignPDF, {
			global: {
				stubs: {
					TopBar: true,
					PdfEditor: true,
					NcNoteCard: true,
					NcButton: true,
				},
				mocks: {
					$route: { name: 'TestRoute', params: { uuid: 'uuid-123' }, query: {} },
				},
			},
		})

		const envelopeFiles = [
			{ id: 10, name: 'file1', file: '/file1.pdf', metadata: { extension: 'pdf' } },
			{ id: 11, name: 'file2', file: '/file2.pdf', metadata: { extension: 'pdf' } },
		]

		vi.mocked(loadState).mockImplementation((app, key, defaultValue) => {
			if (key === 'envelopeFiles') {
				return envelopeFiles
			}
			return defaultValue
		})
		vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
			headers: {
				get: vi.fn(() => 'application/pdf'),
			},
			blob: vi.fn(async () => new Blob(['pdf'], { type: 'application/pdf' })),
		}))

		await wrapper.vm.loadEnvelopePdfs(1)

		expect(signStore.document!.files).toEqual(envelopeFiles)
	})

	it('normalizes envelope visible elements when loading child files', async () => {
		const SignPDF = (await import('../../../views/SignPDF/SignPDF.vue')).default
		const { loadState } = await import('@nextcloud/initial-state')
		const { useSignStore } = await import('../../../store/sign.js')
		const signStore = useSignStore()

		signStore.document = createSignDocument({
			nodeType: 'envelope',
			files: [],
		})

		const wrapper = mount(SignPDF, {
			global: {
				stubs: {
					TopBar: true,
					PdfEditor: true,
					NcNoteCard: true,
					NcButton: true,
				},
				mocks: {
					$route: { name: 'TestRoute', params: { uuid: 'uuid-123' }, query: {} },
				},
			},
		})

		const envelopeFiles = [
			{
				id: '10',
				name: 'file1',
				file: '/file1.pdf',
				metadata: { extension: 'pdf' },
				signers: [
					{
						signRequestId: 501,
						displayName: 'Ada',
						email: 'ada@example.com',
						me: true,
					},
				],
				visibleElements: [
					{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10, top: 20, width: 30, height: 40 } },
					{ fileId: 10, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 99, top: 88, width: 20, height: 10 } },
				],
			},
		]

		vi.mocked(loadState).mockImplementation((app, key, defaultValue) => {
			if (key === 'envelopeFiles') {
				return envelopeFiles
			}
			return defaultValue
		})
		vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
			headers: {
				get: vi.fn(() => 'application/pdf'),
			},
			blob: vi.fn(async () => new Blob(['pdf'], { type: 'application/pdf' })),
		}))

		await wrapper.vm.loadEnvelopePdfs(1)

		expect(wrapper.vm.envelopeFiles).toEqual([
			{
				id: 10,
				name: 'file1',
				file: '/file1.pdf',
				files: undefined,
				metadata: { extension: 'pdf' },
				signers: [
					{
						signRequestId: 501,
						displayName: 'Ada',
						email: 'ada@example.com',
						me: true,
					},
				],
				visibleElements: [
					{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10, top: 20, width: 30, height: 40 } },
				],
			},
		])
	})

	it('hides PDF only when error scope is pdfLoad', async () => {
		const SignPDF = (await import('../../../views/SignPDF/SignPDF.vue')).default
		const { useSignStore } = await import('../../../store/sign.js')
		const signStore = useSignStore()

		signStore.document = createSignDocument()
		signStore.errors = [{ message: 'Document not found', scope: 'pdfLoad' }]

		const wrapper = mount(SignPDF, {
			global: {
				stubs: {
					TopBar: true,
					NcNoteCard: true,
					NcButton: true,
				},
				mocks: {
					$route: { name: 'TestRoute', params: { uuid: 'uuid-123' }, query: {} },
				},
			},
		})

		wrapper.vm.mounted = true
		wrapper.vm.pdfBlobs = [new File([new Blob(['pdf'], { type: 'application/pdf' })], 'sample.pdf', { type: 'application/pdf' })]
		await wrapper.vm.$nextTick()

		expect(wrapper.find('[data-test="pdf-editor"]').exists()).toBe(false)
	})

	it('keeps PDF visible for non-pdfLoad errors', async () => {
		const SignPDF = (await import('../../../views/SignPDF/SignPDF.vue')).default
		const { useSignStore } = await import('../../../store/sign.js')
		const signStore = useSignStore()

		signStore.document = createSignDocument()
		signStore.errors = [{ message: 'Certificate validation failed', code: 422 }]

		const wrapper = mount(SignPDF, {
			global: {
				stubs: {
					TopBar: true,
					NcNoteCard: true,
					NcButton: true,
				},
				mocks: {
					$route: { name: 'TestRoute', params: { uuid: 'uuid-123' }, query: {} },
				},
			},
		})

		wrapper.vm.mounted = true
		wrapper.vm.pdfBlobs = [new File([new Blob(['pdf'], { type: 'application/pdf' })], 'sample.pdf', { type: 'application/pdf' })]
		await wrapper.vm.$nextTick()

		expect(wrapper.find('[data-test="pdf-editor"]').exists()).toBe(true)
	})

	it('uses setSigningErrors with pdfLoad scope when document is missing', async () => {
		const SignPDF = (await import('../../../views/SignPDF/SignPDF.vue')).default
		const { useSignStore } = await import('../../../store/sign.js')
		const signStore = useSignStore()

		signStore.document = undefined
		const setSigningErrorsSpy = vi.spyOn(signStore, 'setSigningErrors')

		const wrapper = mount(SignPDF, {
			global: {
				stubs: {
					TopBar: true,
					NcNoteCard: true,
					NcButton: true,
				},
				mocks: {
					$route: { name: 'TestRoute', params: { uuid: 'uuid-123' }, query: {} },
				},
			},
		})

		await wrapper.vm.loadPdfsFromStore()

		expect(setSigningErrorsSpy).toHaveBeenCalledWith([
			{ message: 'Document not found', scope: 'pdfLoad' },
		])
	})

	it('prefers canonical files collection URL when loading single-file PDF', async () => {
		const SignPDF = (await import('../../../views/SignPDF/SignPDF.vue')).default
		const { useSignStore } = await import('../../../store/sign.js')
		const signStore = useSignStore()
		const fetchMock = vi.fn().mockResolvedValue({
			headers: {
				get: vi.fn(() => 'application/pdf'),
			},
			blob: vi.fn(async () => new Blob(['pdf'], { type: 'application/pdf' })),
		})
		vi.stubGlobal('fetch', fetchMock)

		signStore.document = createSignDocument({
			nodeType: 'file',
			url: '/legacy-root-url.pdf',
			files: [
				{ id: 99, name: 'contract', file: '/canonical-file-url.pdf', metadata: { extension: 'pdf' } },
			],
		})

		const wrapper = mount(SignPDF, {
			global: {
				stubs: {
					TopBar: true,
					NcNoteCard: true,
					NcButton: true,
				},
				mocks: {
					$route: { name: 'TestRoute', params: { uuid: 'uuid-123' }, query: {} },
				},
			},
		})
		await wrapper.vm.$nextTick()
		expect(fetchMock).toHaveBeenCalledWith('/canonical-file-url.pdf')
	})

	it('loads the signing document from file validation when entering the internal sign route', async () => {
		const SignPDF = (await import('../../../views/SignPDF/SignPDF.vue')).default
		const { useFilesStore } = await import('../../../store/files.js')
		const { useSidebarStore } = await import('../../../store/sidebar.js')
		const { useSignStore } = await import('../../../store/sign.js')
		const filesStore = useFilesStore()
		const sidebarStore = useSidebarStore()
		const signStore = useSignStore()
		const file = createSignDocument({
			id: 12,
			status: 1,
			statusText: 'Ready to sign',
			signers: [{ me: true, signRequestId: 44, displayName: 'Admin', email: 'admin@email.tld' }],
		})

		vi.mocked(filesStore.fetchFileDetail).mockResolvedValue(file)
		vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
			headers: {
				get: vi.fn(() => 'application/pdf'),
			},
			blob: vi.fn(async () => new Blob(['pdf'], { type: 'application/pdf' })),
		}))

		mount(SignPDF, {
			global: {
				stubs: {
					TopBar: true,
					PdfEditor: true,
					NcNoteCard: true,
					NcButton: true,
				},
				mocks: {
					$route: { name: 'SignPDF', params: { uuid: 'sign-uuid-123' }, query: {} },
				},
			},
		})

		await vi.waitFor(() => {
			expect(filesStore.fetchFileDetail).toHaveBeenCalledWith({
				uuid: 'sign-uuid-123',
				force: true,
			})
		})

		expect(signStore.document).toEqual(expect.objectContaining({
			id: 12,
			status: 1,
			statusText: 'Ready to sign',
		}))
		expect(filesStore.selectFile).toHaveBeenCalledWith(12)
		expect(sidebarStore.activeSignTab).toHaveBeenCalled()
	})
})
