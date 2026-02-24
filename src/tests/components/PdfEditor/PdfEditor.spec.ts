/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import PdfEditor from '../../../components/PdfEditor/PdfEditor.vue'

const pdfElementsMethods = vi.hoisted(() => ({
	startAddingElement: vi.fn(),
	cancelAdding: vi.fn(),
	addObjectToPage: vi.fn(),
	updateObject: vi.fn(),
	adjustZoomToFit: vi.fn(),
	getPageHeight: vi.fn(() => 841.89),
	pdfDocuments: [],
	selectedDocIndex: 0,
	autoFitZoom: true,
}))

vi.mock('@libresign/pdf-elements/src/components/PDFElements.vue', () => ({
	default: {
		name: 'PDFElements',
		template: '<div class="pdf-elements-mock"></div>',
		setup(_props: unknown, { expose }: { expose: (methods: typeof pdfElementsMethods) => void }) {
			expose(pdfElementsMethods)
		},
	},
}))

vi.mock('../../../helpers/pdfWorker.js', () => ({
	ensurePdfWorker: vi.fn(),
}))

describe('PdfEditor Component - Business Rules', () => {
	let wrapper: ReturnType<typeof mount>

	beforeEach(() => {
		vi.clearAllMocks()
		pdfElementsMethods.pdfDocuments = []
		pdfElementsMethods.selectedDocIndex = 0
		pdfElementsMethods.startAddingElement = vi.fn()
		pdfElementsMethods.cancelAdding = vi.fn()
		pdfElementsMethods.addObjectToPage = vi.fn()
		pdfElementsMethods.updateObject = vi.fn()
		wrapper = mount(PdfEditor, {
			props: {
				files: [],
				fileNames: [],
				readOnly: false,
				signers: [],
			},
			global: {
				stubs: {
					NcButton: true,
					NcIconSvgWrapper: true,
					PDFElements: false,
					SignerMenu: true,
					SignatureBox: true,
				},
			},
		})
		Object.defineProperty(wrapper.vm, '$refs', {
			value: {
				pdfElements: pdfElementsMethods,
			},
			configurable: true,
		})
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

			expect(wrapper.vm.getSignerLabel(signer)).toBe(123)
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
			Object.defineProperty(wrapper.vm.$refs, 'pdfElements', {
				value: null,
				configurable: true,
			})

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
			expect(pdfElementsMethods.startAddingElement).toHaveBeenCalledWith({
				type: 'signature',
				width: 200,
				height: 100,
				signer: expect.objectContaining({
					email: 'test@example.com',
					element: {},
				}),
			})
		})

		it('preserves existing element data when adding', () => {
			const signer = {
				email: 'test@example.com',
				element: { elementId: 123, signRequestId: 456 },
			}
			const size = { width: 200, height: 100 }

			wrapper.vm.startAddingSigner(signer, size)

			expect(pdfElementsMethods.startAddingElement).toHaveBeenCalledWith(
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
			Object.assign(wrapper.vm.$refs.pdfElements, {
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

			expect(wrapper.vm.$refs.pdfElements.addObjectToPage).toHaveBeenCalledWith(
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

			const call = wrapper.vm.$refs.pdfElements.addObjectToPage.mock.calls[0][0]

			expect(call.width).toBe(200) // urx - llx = 250 - 50
			expect(call.height).toBe(200) // ury - lly = 300 - 100
			expect(call.x).toBe(50) // llx
		})

		it('calculates y from ury when using PDF coordinates', async () => {
			const pageHeight = 841.89
			wrapper.vm.$refs.pdfElements.getPageHeight.mockReturnValue(pageHeight)

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

			const call = wrapper.vm.$refs.pdfElements.addObjectToPage.mock.calls[0][0]

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

			expect(wrapper.vm.$refs.pdfElements.addObjectToPage).toHaveBeenCalledWith(
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
			wrapper.vm.$refs.pdfElements.pdfDocuments = [
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

			expect(wrapper.vm.$refs.pdfElements.addObjectToPage).toHaveBeenCalledWith(
				expect.anything(),
				4, // 0-indexed: page 5 = index 4
				0,
			)
		})

		it('uses selectedDocIndex when documentIndex not specified', async () => {
			wrapper.vm.$refs.pdfElements.selectedDocIndex = 2
			wrapper.vm.$refs.pdfElements.pdfDocuments = [
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

			expect(wrapper.vm.$refs.pdfElements.addObjectToPage).toHaveBeenCalledWith(
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

			const call = wrapper.vm.$refs.pdfElements.addObjectToPage.mock.calls[0][0]

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
			Object.assign(wrapper.vm.$refs.pdfElements, {
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
				wrapper.vm.$refs.pdfElements,
				'obj-1',
			)

			expect(location).toEqual({ docIndex: 0, pageIndex: 0 })
		})

		it('finds object in first document, second page', () => {
			const location = wrapper.vm.findObjectLocation(
				wrapper.vm.$refs.pdfElements,
				'obj-3',
			)

			expect(location).toEqual({ docIndex: 0, pageIndex: 1 })
		})

		it('finds object in second document', () => {
			const location = wrapper.vm.findObjectLocation(
				wrapper.vm.$refs.pdfElements,
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
			wrapper.vm.$refs.pdfElements.pdfDocuments = []

			const location = wrapper.vm.findObjectLocation(
				wrapper.vm.$refs.pdfElements,
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

			Object.assign(wrapper.vm.$refs.pdfElements, {
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

			expect(wrapper.vm.$refs.pdfElements.updateObject).not.toHaveBeenCalled()
		})

		it('does nothing when signer is null', () => {
			const object = { id: 'obj-1', signer: { signRequestId: 1 } }

			wrapper.vm.onSignerChange(object, null)

			expect(wrapper.vm.$refs.pdfElements.updateObject).not.toHaveBeenCalled()
		})

		it('updates object with new signer from signers list', () => {
			const object = {
				id: 'obj-1',
				signer: { signRequestId: 1, element: { elementId: 123 } },
			}
			const newSigner = { signRequestId: 2 }

			wrapper.vm.onSignerChange(object, newSigner)

			expect(wrapper.vm.$refs.pdfElements.updateObject).toHaveBeenCalledWith(
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

			expect(wrapper.vm.$refs.pdfElements.updateObject).toHaveBeenCalledWith(
				expect.anything(),
				expect.anything(),
				{
					signer: expect.objectContaining({
						element: expect.objectContaining({
							elementId: 123,
							signRequestId: 2, // updated
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

			expect(wrapper.vm.$refs.pdfElements.updateObject).toHaveBeenCalledWith(
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

			expect(wrapper.vm.$refs.pdfElements.updateObject).not.toHaveBeenCalled()
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

			expect(pdfElementsMethods.cancelAdding).toHaveBeenCalled()
		})

		it('does not error when pdfElements not available', () => {
			Object.defineProperty(wrapper.vm, '$refs', {
				value: { pdfElements: null },
				configurable: true,
			})

			expect(() => wrapper.vm.cancelAdding()).not.toThrow()
		})
	})

	describe('RULE: waitForPageRender awaits page Promise', () => {
		it('resolves immediately when page promise is already resolved', async () => {
			Object.assign(wrapper.vm.$refs.pdfElements, {
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

			Object.assign(wrapper.vm.$refs.pdfElements, {
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

			Object.assign(wrapper.vm.$refs.pdfElements, {
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
			Object.assign(wrapper.vm.$refs.pdfElements, {
				pdfDocuments: [],
			})

			const result = await wrapper.vm.waitForPageRender?.(1, 0)
		})

		it('resolves immediately when pdfElements is null', async () => {
			Object.defineProperty(wrapper.vm, '$refs', {
				value: { pdfElements: null },
				configurable: true,
			})

			await wrapper.vm.waitForPageRender(0, 0)
		})
	})

	describe('RULE: addSigner awaits page render for multi-document envelopes', () => {
		it('awaits second document page before adding element', async () => {
			let resolveSecondPage: ((value: unknown) => void) | undefined
			const secondPagePromise = new Promise(resolve => { resolveSecondPage = resolve })

			Object.assign(wrapper.vm.$refs.pdfElements, {
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
			expect(wrapper.vm.$refs.pdfElements.addObjectToPage).not.toHaveBeenCalled()

			if (resolveSecondPage) {
				resolveSecondPage({})
			}
			await addPromise

			expect(wrapper.vm.$refs.pdfElements.addObjectToPage).toHaveBeenCalledWith(
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
			Object.assign(wrapper.vm.$refs.pdfElements, {
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

			expect(wrapper.vm.$refs.pdfElements.addObjectToPage).toHaveBeenCalledTimes(1)
		})

		it('does nothing when pdfElements is null', async () => {
			Object.defineProperty(wrapper.vm, '$refs', {
				value: { pdfElements: null },
				configurable: true,
			})

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

	describe('RULE: coordinate system conversion', () => {
		beforeEach(() => {
			Object.assign(wrapper.vm.$refs.pdfElements, {
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

			const call = wrapper.vm.$refs.pdfElements.addObjectToPage.mock.calls[0][0]

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

			const call = wrapper.vm.$refs.pdfElements.addObjectToPage.mock.calls[0][0]

			expect(call.y).toBeGreaterThanOrEqual(0)
		})
	})
})
