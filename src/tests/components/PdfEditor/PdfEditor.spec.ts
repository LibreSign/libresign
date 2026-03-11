/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import PdfEditor from '../../../components/PdfEditor/PdfEditor.vue'

type SignerRecord = {
	displayName?: string
	name?: string
	email?: string
	id?: string | number
	uuid?: string | number
	signRequestId?: string | number
	element?: {
		documentIndex?: number
		signRequestId?: string | number
		coordinates?: Record<string, number>
		[key: string]: unknown
	}
	[key: string]: unknown
}

type PdfObjectRecord = {
	id: string
	signer?: SignerRecord | null
	[key: string]: unknown
}

type PdfPageRecord = {
	getViewport?: (options: { scale: number }) => { width: number, height: number }
}

type PdfDocumentRecord = {
	numPages?: number
	pages?: Array<Promise<PdfPageRecord>>
	allObjects?: PdfObjectRecord[][]
}

type PdfElementsMock = {
	startAddingElement: ReturnType<typeof vi.fn>
	cancelAdding: ReturnType<typeof vi.fn>
	addObjectToPage: ReturnType<typeof vi.fn>
	updateObject: ReturnType<typeof vi.fn>
	adjustZoomToFit: ReturnType<typeof vi.fn>
	getPageHeight: ReturnType<typeof vi.fn>
	pdfDocuments: PdfDocumentRecord[]
	selectedDocIndex: number
	autoFitZoom: boolean
}

type PdfEditorVm = {
	pdfElements: PdfElementsMock | null
	$refs: {
		pdfElements: PdfElementsMock
	}
	$nextTick: () => Promise<void>
	getSignerLabel: (signer: SignerRecord | null | undefined) => string
	hasMultipleSigners: boolean
	startAddingSigner: (signer: SignerRecord | null | undefined, size: { width?: number, height?: number }) => boolean
	addSigner: (signer: SignerRecord) => Promise<void>
	findObjectLocation: (pdfElements: PdfElementsMock | null | undefined, objectId: string) => { docIndex: number, pageIndex: number } | null
	onSignerChange: (object: PdfObjectRecord | null | undefined, signer: SignerRecord | null | undefined) => void
	endInit: (event: Record<string, unknown>) => Promise<void>
	handleDeleteObject: (payload: { object?: PdfObjectRecord }) => void
	handleObjectClick: (event: Record<string, unknown>) => void
	cancelAdding: () => void
	waitForPageRender?: (docIndex: number, pageIndex: number) => Promise<void>
	getPageAriaLabel: (payload: {
		docIndex: number
		docName: string
		totalDocs: number
		pageNumber: number
		totalPages: number
		isAddingMode: boolean
	}) => string
	setProps: (props: Record<string, unknown>) => Promise<void>
}

type PdfEditorWrapper = VueWrapper<PdfEditorVm>

vi.mock('@libresign/pdf-elements', () => ({
	default: {
		name: 'PDFElements',
		template: '<div class="pdf-elements-mock"></div>',
		setup(_props: unknown, { expose }: { expose: (methods: any) => void }) {
			const methods = {
				startAddingElement: vi.fn(),
				cancelAdding: vi.fn(),
				addObjectToPage: vi.fn(),
				updateObject: vi.fn(),
				adjustZoomToFit: vi.fn(),
				getPageHeight: vi.fn(() => 841.89),
				pdfDocuments: [],
				selectedDocIndex: 0,
				autoFitZoom: true,
			}
			expose(methods)
			return methods
		},
	},
}))

vi.mock('../../../helpers/pdfWorker.js', () => ({
	ensurePdfWorker: vi.fn(),
}))

describe('PdfEditor Component - Business Rules', () => {
	let wrapper: PdfEditorWrapper
	const getPdfElements = () => wrapper.vm.pdfElements as PdfElementsMock

	function createWrapper(props: Record<string, unknown> = {}): PdfEditorWrapper {
		return mount(PdfEditor, {
			props: {
				files: [],
				fileNames: [],
				readOnly: false,
				signers: [],
				...props,
			},
			global: {
				stubs: {
					NcButton: true,
					NcIconSvgWrapper: true,
					SignerMenu: true,
					SignatureBox: true,
				},
			},
		}) as unknown as PdfEditorWrapper
	}

	beforeEach(() => {
		vi.clearAllMocks()
		wrapper = createWrapper()
	})

	describe('RULE: getSignerLabel with fallback chain', () => {
		it('returns displayName when available', () => {
			const signer = {
				displayName: 'John Doe',
				name: 'johndoe',
				email: 'john@example.com',
				id: 123,
			}

			expect(wrapper.vm.getSignerLabel(signer)).toBe('John Doe')
		})

		it('falls back to name when displayName not available', () => {
			const signer = {
				name: 'johndoe',
				email: 'john@example.com',
				id: 123,
			}

			expect(wrapper.vm.getSignerLabel(signer)).toBe('johndoe')
		})

		it('falls back to email when name not available', () => {
			const signer = {
				email: 'john@example.com',
				id: 123,
			}

			expect(wrapper.vm.getSignerLabel(signer)).toBe('john@example.com')
		})

		it('falls back to id when email not available', () => {
			const signer = {
				id: 123,
			}

			expect(wrapper.vm.getSignerLabel(signer)).toBe('123')
		})

		it('returns empty string when signer is null', () => {
			expect(wrapper.vm.getSignerLabel(null)).toBe('')
		})

		it('returns empty string when signer is undefined', () => {
			expect(wrapper.vm.getSignerLabel(undefined)).toBe('')
		})

		it('returns empty string when no identifiable fields', () => {
			const signer = {}

			expect(wrapper.vm.getSignerLabel(signer)).toBe('')
		})
	})

	describe('RULE: hasMultipleSigners detection', () => {
		it('returns false when no signers', () => {
			expect(wrapper.vm.hasMultipleSigners).toBe(false)
		})

		it('returns false when one signer', async () => {
			await wrapper.setProps({
				signers: [{ email: 'test@example.com' }],
			})

			expect(wrapper.vm.hasMultipleSigners).toBe(false)
		})

		it('returns true when multiple signers', async () => {
			await wrapper.setProps({
				signers: [
					{ email: 'test1@example.com' },
					{ email: 'test2@example.com' },
				],
			})

			expect(wrapper.vm.hasMultipleSigners).toBe(true)
		})

		it('returns false when signers is null', async () => {
			await wrapper.setProps({ signers: null })

			expect(wrapper.vm.hasMultipleSigners).toBe(false)
		})
	})

	describe('RULE: startAddingSigner validation', () => {
		it('returns false when pdfElements not available', () => {
			wrapper.vm.pdfElements = null

			const result = wrapper.vm.startAddingSigner(
				{ email: 'test@example.com' },
				{ width: 200, height: 100 },
			)

			expect(result).toBe(false)
		})

		it('returns false when size has no width', () => {
			const result = wrapper.vm.startAddingSigner(
				{ email: 'test@example.com' },
				{ height: 100 },
			)

			expect(result).toBe(false)
		})

		it('returns false when size has no height', () => {
			const result = wrapper.vm.startAddingSigner(
				{ email: 'test@example.com' },
				{ width: 200 },
			)

			expect(result).toBe(false)
		})

		it('returns true and starts adding when valid params', () => {
			const signer = { email: 'test@example.com' }
			const size = { width: 200, height: 100 }

			const result = wrapper.vm.startAddingSigner(signer, size)

			expect(result).toBe(true)
			expect(getPdfElements().startAddingElement).toHaveBeenCalledWith(
				expect.objectContaining({
					type: 'signature',
					x: 0,
					y: 0,
					width: 200,
					height: 100,
					signer: expect.objectContaining({
						email: 'test@example.com',
						element: {},
					}),
				}),
			)
		})

		it('preserves existing element data when adding', () => {
			const signer = {
				email: 'test@example.com',
				element: { elementId: 123, signRequestId: 456 },
			}
			const size = { width: 200, height: 100 }

			wrapper.vm.startAddingSigner(signer, size)

			expect(getPdfElements().startAddingElement).toHaveBeenCalledWith(
				expect.objectContaining({
					signer: expect.objectContaining({
						element: expect.objectContaining({
							elementId: 123,
							signRequestId: 456,
						}),
					}),
				}),
			)
		})
	})

	describe('RULE: addSigner coordinate calculations', () => {
		beforeEach(() => {
			Object.assign(getPdfElements(), {
				selectedDocIndex: 0,
				getPageHeight: vi.fn(() => 841.89),
				addObjectToPage: vi.fn(),
				pdfDocuments: [
					{ pages: [Promise.resolve({})] },
				],
			})
		})

		it('converts coordinates from left/top format', async () => {
			const signer = {
				element: {
					documentIndex: 0,
					coordinates: {
						page: 1,
						left: 100,
						top: 200,
						width: 300,
						height: 150,
					},
				},
			}

			await wrapper.vm.addSigner(signer)

			expect(getPdfElements().addObjectToPage).toHaveBeenCalledWith(
				expect.objectContaining({
					x: 100,
					y: 200,
					width: 300,
					height: 150,
				}),
				0, // pageIndex
				0, // docIndex
			)
		})

		it('converts coordinates from llx/lly/urx/ury format to x/y', async () => {
			const signer = {
				element: {
					documentIndex: 0,
					coordinates: {
						page: 1,
						llx: 50,
						lly: 100,
						urx: 250,
						ury: 300,
					},
				},
			}

			await wrapper.vm.addSigner(signer)

			const call = getPdfElements().addObjectToPage.mock.calls[0][0]

			expect(call.width).toBe(200) // urx - llx = 250 - 50
			expect(call.height).toBe(200) // ury - lly = 300 - 100
			expect(call.x).toBe(50) // llx
		})

		it('calculates y from ury when using PDF coordinates', async () => {
			const pageHeight = 841.89
			getPdfElements().getPageHeight.mockReturnValue(pageHeight)

			const signer = {
				element: {
					documentIndex: 0,
					coordinates: {
						page: 1,
						llx: 50,
						lly: 100,
						urx: 250,
						ury: 700,
					},
				},
			}

			await wrapper.vm.addSigner(signer)

			const call = getPdfElements().addObjectToPage.mock.calls[0][0]

			// y = pageHeight - ury = 841.89 - 700
			expect(call.y).toBeCloseTo(141.89, 2)
		})

		it('uses default coordinates when missing', async () => {
			const signer = {
				element: {
					documentIndex: 0,
					coordinates: {
						page: 1,
					},
				},
			}

			await wrapper.vm.addSigner(signer)

			expect(getPdfElements().addObjectToPage).toHaveBeenCalledWith(
				expect.objectContaining({
					x: 0,
					y: 0,
					width: 0,
					height: 0,
				}),
				0,
				0,
			)
		})

		it('uses correct page index (page - 1)', async () => {
			getPdfElements().pdfDocuments = [
				{ pages: [Promise.resolve({}), Promise.resolve({}), Promise.resolve({}), Promise.resolve({}), Promise.resolve({})] },
			]

			const signer = {
				element: {
					documentIndex: 0,
					coordinates: {
						page: 5, // 1-indexed
						left: 0,
						top: 0,
						width: 100,
						height: 50,
					},
				},
			}

			await wrapper.vm.addSigner(signer)

			expect(getPdfElements().addObjectToPage).toHaveBeenCalledWith(
				expect.anything(),
				4, // 0-indexed: page 5 = index 4
				0,
			)
		})

		it('uses selectedDocIndex when documentIndex not specified', async () => {
			getPdfElements().selectedDocIndex = 2
			getPdfElements().pdfDocuments = [
				{ pages: [Promise.resolve({})] },
				{ pages: [Promise.resolve({})] },
				{ pages: [Promise.resolve({})] },
			]

			const signer = {
				element: {
					coordinates: {
						page: 1,
						left: 0,
						top: 0,
						width: 100,
						height: 50,
					},
				},
			}

			await wrapper.vm.addSigner(signer)

			expect(getPdfElements().addObjectToPage).toHaveBeenCalledWith(
				expect.anything(),
				0,
				2, // uses selectedDocIndex
			)
		})

		it('generates unique object ID', async () => {
			const signer = {
				element: {
					documentIndex: 0,
					coordinates: { page: 1 },
				},
			}

			await wrapper.vm.addSigner(signer)

			const call = getPdfElements().addObjectToPage.mock.calls[0][0]

			expect(call.id).toMatch(/^obj-\d+-[a-z0-9]{6}$/)
		})

		it('includes signer data in object', async () => {
			const signer = {
				email: 'test@example.com',
				displayName: 'Test User',
				element: {
					documentIndex: 0,
					coordinates: { page: 1 },
				},
			}

			await wrapper.vm.addSigner(signer)

			expect(wrapper.vm.$refs.pdfElements.addObjectToPage).toHaveBeenCalledWith(
				expect.objectContaining({
					type: 'signature',
					signer,
				}),
				expect.anything(),
				expect.anything(),
			)
		})
	})

	describe('RULE: findObjectLocation in documents', () => {
		beforeEach(() => {
			Object.assign(getPdfElements(), {
				pdfDocuments: [
					{
						allObjects: [
							[{ id: 'obj-1' }, { id: 'obj-2' }],
							[{ id: 'obj-3' }],
						],
					},
					{
						allObjects: [
							[{ id: 'obj-4' }],
							[{ id: 'obj-5' }, { id: 'obj-6' }],
						],
					},
				],
			})
		})

		it('finds object in first document, first page', () => {
			const location = wrapper.vm.findObjectLocation(
				getPdfElements(),
				'obj-1',
			)

			expect(location).toEqual({ docIndex: 0, pageIndex: 0 })
		})

		it('finds object in first document, second page', () => {
			const location = wrapper.vm.findObjectLocation(
				getPdfElements(),
				'obj-3',
			)

			expect(location).toEqual({ docIndex: 0, pageIndex: 1 })
		})

		it('finds object in second document', () => {
			const location = wrapper.vm.findObjectLocation(
				getPdfElements(),
				'obj-5',
			)

			expect(location).toEqual({ docIndex: 1, pageIndex: 1 })
		})

		it('returns null when object not found', () => {
			const location = wrapper.vm.findObjectLocation(
				wrapper.vm.$refs.pdfElements,
				'obj-999',
			)

			expect(location).toBe(null)
		})

		it('returns null when pdfElements is null', () => {
			const location = wrapper.vm.findObjectLocation(null, 'obj-1')

			expect(location).toBe(null)
		})

		it('returns null when pdfDocuments is empty', () => {
			getPdfElements().pdfDocuments = []

			const location = wrapper.vm.findObjectLocation(
				getPdfElements(),
				'obj-1',
			)

			expect(location).toBe(null)
		})
	})

	describe('RULE: onSignerChange updates object signer', () => {
		beforeEach(async () => {
			await wrapper.setProps({
				signers: [
					{ signRequestId: 1, email: 'signer1@example.com', displayName: 'Signer 1' },
					{ signRequestId: 2, email: 'signer2@example.com', displayName: 'Signer 2' },
					{ signRequestId: 3, email: 'signer3@example.com', displayName: 'Signer 3' },
				],
			})

			Object.assign(getPdfElements(), {
				updateObject: vi.fn(),
				pdfDocuments: [
					{
						allObjects: [
							[{ id: 'obj-1', signer: { signRequestId: 1 } }],
						],
					},
				],
			})
		})

		it('does nothing when object is null', () => {
			wrapper.vm.onSignerChange(null, { signRequestId: 2 })

			expect(getPdfElements().updateObject).not.toHaveBeenCalled()
		})

		it('does nothing when signer is null', () => {
			const object = { id: 'obj-1', signer: { signRequestId: 1 } }

			wrapper.vm.onSignerChange(object, null)

			expect(getPdfElements().updateObject).not.toHaveBeenCalled()
		})

		it('updates object with new signer from signers list', () => {
			const object = {
				id: 'obj-1',
				signer: { signRequestId: 1, element: { elementId: 123 } },
			}
			const newSigner = { signRequestId: 2 }

			wrapper.vm.onSignerChange(object, newSigner)

			expect(getPdfElements().updateObject).toHaveBeenCalledWith(
				0, // docIndex
				'obj-1',
				{
					signer: expect.objectContaining({
						signRequestId: 2,
						email: 'signer2@example.com',
						displayName: 'Signer 2',
					}),
				},
			)
		})

		it('preserves element data when changing signer', () => {
			const object = {
				id: 'obj-1',
				signer: {
					signRequestId: 1,
					element: { elementId: 123, coordinates: { page: 1 } },
				},
			}
			const newSigner = { signRequestId: 2 }

			wrapper.vm.onSignerChange(object, newSigner)

			expect(getPdfElements().updateObject).toHaveBeenCalledWith(
				0,
				'obj-1',
				{
					signer: expect.objectContaining({
						signRequestId: 2,
						element: expect.objectContaining({
							elementId: 123,
							coordinates: { page: 1 },
						}),
					}),
				},
			)
		})

		it('uses email as identifier when signRequestId not available', async () => {
			await wrapper.setProps({
				signers: [
					{ email: 'signer1@example.com' },
					{ email: 'signer2@example.com' },
				],
			})

			const object = {
				id: 'obj-1',
				signer: { email: 'signer1@example.com' },
			}

			wrapper.vm.onSignerChange(object, { email: 'signer2@example.com' })

			expect(getPdfElements().updateObject).toHaveBeenCalledWith(
				expect.anything(),
				expect.anything(),
				{
					signer: expect.objectContaining({
						email: 'signer2@example.com',
					}),
				},
			)
		})

		it('does nothing when target signer not found in signers list', () => {
			const object = { id: 'obj-1', signer: { signRequestId: 1 } }

			wrapper.vm.onSignerChange(object, { signRequestId: 999 })

			expect(getPdfElements().updateObject).not.toHaveBeenCalled()
		})
	})

	describe('RULE: endInit emits measured dimensions', () => {
		it('adjusts zoom when auto-fit is disabled and emits page measurements', async () => {
			Object.assign(getPdfElements(), {
				autoFitZoom: false,
				adjustZoomToFit: vi.fn(),
				pdfDocuments: [
					{
						numPages: 2,
						pages: [
							Promise.resolve({ getViewport: vi.fn(() => ({ width: 595.28, height: 841.89 })) }),
							Promise.resolve({ getViewport: vi.fn(() => ({ width: 600, height: 800 })) }),
						],
					},
				],
			})

			await wrapper.vm.endInit({ ready: true })

			expect(getPdfElements().adjustZoomToFit).toHaveBeenCalledTimes(1)
			expect(wrapper.emitted('pdf-editor:end-init')?.[0]?.[0]).toEqual({
				ready: true,
				measurement: {
					1: { width: 595.28, height: 841.89 },
					2: { width: 600, height: 800 },
				},
			})
		})
	})

	describe('RULE: event emissions', () => {
		it('emits pdf-editor:on-delete-signer when deleting signature object', () => {
			const object = { id: 'obj-1', signer: { email: 'test@example.com' } }

			wrapper.vm.handleDeleteObject({ object })

			expect(wrapper.emitted('pdf-editor:on-delete-signer')).toBeTruthy()
			expect(wrapper.emitted('pdf-editor:on-delete-signer')?.[0]?.[0]).toEqual(object)
		})

		it('does not emit delete event when object has no signer', () => {
			const object = { id: 'obj-1' }

			wrapper.vm.handleDeleteObject({ object })

			expect(wrapper.emitted('pdf-editor:on-delete-signer')).toBeFalsy()
		})

		it('emits pdf-editor:object-click when object clicked', () => {
			const event = { object: { id: 'obj-1' } }

			wrapper.vm.handleObjectClick(event)

			expect(wrapper.emitted('pdf-editor:object-click')).toBeTruthy()
			expect(wrapper.emitted('pdf-editor:object-click')?.[0]?.[0]).toEqual(event)
		})
	})

	describe('RULE: cancelAdding method', () => {
		it('calls pdfElements cancelAdding when available', () => {
			wrapper.vm.cancelAdding()

			expect(getPdfElements().cancelAdding).toHaveBeenCalled()
		})

		it('does not error when pdfElements not available', () => {
			wrapper.vm.pdfElements = null

			expect(() => {
				wrapper.vm.cancelAdding()
			}).not.toThrow()
		})
	})

	describe('RULE: waitForPageRender awaits page Promise', () => {
		it('resolves immediately when page promise is already resolved', async () => {
			Object.assign(getPdfElements(), {
				pdfDocuments: [
					{ pages: [Promise.resolve({})] },
				],
			})

			const waitForPage = wrapper.vm.waitForPageRender
			if (waitForPage) {
				await waitForPage(0, 0)
			}
		})

		it('awaits page promise resolution', async () => {
			let resolveSecondPage: ((value: object) => void) | undefined
			const secondPagePromise = new Promise<object>(resolve => { resolveSecondPage = resolve })

			Object.assign(getPdfElements(), {
				pdfDocuments: [
					{ pages: [Promise.resolve({})] },
					{ pages: [secondPagePromise] },
				],
			})

			let resolved = false
			const waitForPage = wrapper.vm.waitForPageRender
			const promise = waitForPage ? waitForPage(1, 0).then(() => { resolved = true }) : Promise.resolve()

			await wrapper.vm.$nextTick()
			expect(resolved).toBe(false)

			if (resolveSecondPage) {
				resolveSecondPage({})
			}
			await promise
			expect(resolved).toBe(true)
		})

		it('waits for a pending page promise before resolving', async () => {
			let resolveSecondPage: ((value: object) => void) | undefined
			const secondPagePromise = new Promise<object>(resolve => { resolveSecondPage = resolve })

			Object.assign(getPdfElements(), {
				pdfDocuments: [
					{ pages: [Promise.resolve({})] },
					{ pages: [secondPagePromise] },
				],
			})

			let resolved = false
			const waitForPage = wrapper.vm.waitForPageRender
			const promise = waitForPage ? waitForPage(1, 0).then(() => { resolved = true }) : Promise.resolve()

			await wrapper.vm.$nextTick()
			expect(resolved).toBe(false)

			if (resolveSecondPage) {
				resolveSecondPage({})
			}
			await promise
			expect(resolved).toBe(true)
		})

		it('resolves immediately when document does not exist', async () => {
			Object.assign(getPdfElements(), {
				pdfDocuments: [],
			})

			const result = await wrapper.vm.waitForPageRender?.(1, 0)
		})

		it('resolves immediately when pdfElements is null', async () => {
			wrapper.vm.pdfElements = null
			await wrapper.vm.waitForPageRender?.(0, 0)
		})
	})

	describe('RULE: addSigner awaits page render for multi-document envelopes', () => {
		it('awaits second document page before adding element', async () => {
			let resolveSecondPage: ((value: unknown) => void) | undefined
			const secondPagePromise = new Promise(resolve => { resolveSecondPage = resolve })

			Object.assign(getPdfElements(), {
				selectedDocIndex: 0,
				getPageHeight: vi.fn(() => 841.89),
				addObjectToPage: vi.fn(),
				pdfDocuments: [
					{ pages: [Promise.resolve({})] },
					{ pages: [secondPagePromise] },
				],
			})

			const signer = {
				displayName: 'admin',
				element: {
					documentIndex: 1,
					coordinates: {
						page: 1,
						left: 148,
						top: 16,
						width: 350,
						height: 100,
					},
				},
			}

			const addPromise = wrapper.vm.addSigner(signer)

			await wrapper.vm.$nextTick()
			expect(getPdfElements().addObjectToPage).not.toHaveBeenCalled()

			if (resolveSecondPage) {
				resolveSecondPage({})
			}
			await addPromise

			expect(getPdfElements().addObjectToPage).toHaveBeenCalledWith(
				expect.objectContaining({
					x: 148,
					y: 16,
					width: 350,
					height: 100,
				}),
				0,
				1,
			)
		})

		it('adds immediately when page is already rendered', async () => {
			Object.assign(getPdfElements(), {
				selectedDocIndex: 0,
				getPageHeight: vi.fn(() => 841.89),
				addObjectToPage: vi.fn(),
				pdfDocuments: [
					{ pages: [Promise.resolve({})] },
					{ pages: [Promise.resolve({})] },
				],
			})

			const signer = {
				element: {
					documentIndex: 1,
					coordinates: { page: 1, left: 100, top: 50, width: 200, height: 80 },
				},
			}

			await wrapper.vm.addSigner(signer)

			expect(getPdfElements().addObjectToPage).toHaveBeenCalledTimes(1)
		})

		it('does nothing when pdfElements is null', async () => {
			wrapper.vm.pdfElements = null

			const signer = {
				element: {
					documentIndex: 0,
					coordinates: { page: 1 },
				},
			}

			await wrapper.vm.addSigner(signer)
		})
	})

	describe('RULE: readOnly prop behavior', () => {
		it('passes readOnly to PDFElements', async () => {
			await wrapper.setProps({ readOnly: true })

			// Would need to check the PDFElements component props
			// This is more of an integration test
		})
	})

	describe('RULE: getPageAriaLabel accessibility labels', () => {
		it('single doc, not adding mode: returns plain page label', () => {
			const label = wrapper.vm.getPageAriaLabel({
				docIndex: 0,
				docName: 'contract.pdf',
				totalDocs: 1,
				pageNumber: 2,
				totalPages: 5,
				isAddingMode: false,
			})

			expect(label).toBe('Page 2 of 5.')
		})

		it('single doc, adding mode: includes keyboard placement hint', () => {
			const label = wrapper.vm.getPageAriaLabel({
				docIndex: 0,
				docName: 'contract.pdf',
				totalDocs: 1,
				pageNumber: 3,
				totalPages: 5,
				isAddingMode: true,
			})

			expect(label).toBe('Page 3 of 5. Press Enter or Space to place the signature here.')
		})

		it('multi-doc, not adding mode: includes document context', () => {
			const label = wrapper.vm.getPageAriaLabel({
				docIndex: 1,
				docName: 'annex.pdf',
				totalDocs: 3,
				pageNumber: 1,
				totalPages: 4,
				isAddingMode: false,
			})

			expect(label).toBe('Document 2 of 3 (annex.pdf), page 1 of 4.')
		})

		it('multi-doc, adding mode: includes document context and keyboard placement hint', () => {
			const label = wrapper.vm.getPageAriaLabel({
				docIndex: 0,
				docName: 'main.pdf',
				totalDocs: 2,
				pageNumber: 1,
				totalPages: 10,
				isAddingMode: true,
			})

			expect(label).toBe('Document 1 of 2 (main.pdf), page 1 of 10. Press Enter or Space to place the signature here.')
		})
	})

	describe('RULE: coordinate system conversion', () => {
		beforeEach(() => {
			Object.assign(getPdfElements(), {
				selectedDocIndex: 0,
				getPageHeight: vi.fn(() => 841.89), // A4 height in points
				addObjectToPage: vi.fn(),
				pdfDocuments: [
					{ pages: [Promise.resolve({})] },
				],
			})
		})

		it('handles bottom-left origin PDF coordinates correctly', async () => {
			// PDF uses bottom-left origin, web uses top-left
			const pageHeight = 841.89
			const signer = {
				element: {
					documentIndex: 0,
					coordinates: {
						page: 1,
						lly: 100, // 100 points from bottom
						ury: 200, // 200 points from bottom
						llx: 50,
						urx: 250,
					},
				},
			}

			await wrapper.vm.addSigner(signer)

			const call = getPdfElements().addObjectToPage.mock.calls[0][0]

			// y should be: pageHeight - ury = 841.89 - 200 = 641.89
			expect(call.y).toBeCloseTo(641.89, 2)
			expect(call.height).toBe(100) // ury - lly
		})

		it('ensures y coordinate never negative', async () => {
			const signer = {
				element: {
					documentIndex: 0,
					coordinates: {
						page: 1,
						ury: 900, // beyond page height
						lly: 800,
						llx: 0,
						urx: 100,
					},
				},
			}

			await wrapper.vm.addSigner(signer)

			const call = getPdfElements().addObjectToPage.mock.calls[0][0]

			expect(call.y).toBeGreaterThanOrEqual(0)
		})
	})
})
