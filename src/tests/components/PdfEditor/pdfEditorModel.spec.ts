/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	buildPdfEditorSignerPayload,
	calculatePdfPlacement,
	createPdfEditorObject,
	createPdfObjectId,
	findPdfObjectLocation,
	getPdfEditorSignerId,
	getPdfEditorSignerLabel,
	resolvePdfEditorSignerChange,
} from '../../../components/PdfEditor/pdfEditorModel'

describe('pdfEditorModel', () => {
	describe('RULE: signer identity is derived from a single stable key', () => {
		it('prefers signRequestId over email', () => {
			expect(getPdfEditorSignerId({ signRequestId: 15, email: 'fallback@example.com' })).toBe('15')
		})

		it('falls back to email when signRequestId is absent', () => {
			expect(getPdfEditorSignerId({ email: 'fallback@example.com' })).toBe('fallback@example.com')
		})

		it('returns empty string when signer has no identity', () => {
			expect(getPdfEditorSignerId({})).toBe('')
		})
	})

	describe('RULE: signer labels come from a single presentation rule', () => {
		it('prefers displayName', () => {
			expect(getPdfEditorSignerLabel({ displayName: 'Ada', email: 'ada@example.com', signRequestId: 9 })).toBe('Ada')
		})

		it('falls back to identity fields', () => {
			expect(getPdfEditorSignerLabel({ email: 'ada@example.com' })).toBe('ada@example.com')
			expect(getPdfEditorSignerLabel({ signRequestId: 9 })).toBe('9')
		})
	})

	describe('RULE: signer payload cloning never mutates caller input', () => {
		it('ensures a detached element object exists', () => {
			const source = { email: 'ada@example.com' }

			const payload = buildPdfEditorSignerPayload(source)

			expect(payload).toEqual({ email: 'ada@example.com', element: {} })
			expect(payload).not.toBe(source)
		})

		it('preserves placement data by value', () => {
			const source = {
				email: 'ada@example.com',
				element: {
					elementId: 9,
					coordinates: { page: 1, left: 10, top: 20 },
				},
			}

			const payload = buildPdfEditorSignerPayload(source)

			expect(payload.element).toEqual(source.element)
			expect(payload.element).not.toBe(source.element)
		})
	})

	describe('RULE: object lookup is a pure document traversal', () => {
		it('finds the object document and page indexes', () => {
			const location = findPdfObjectLocation([
				{ allObjects: [[{ id: 'obj-1' }], [{ id: 'obj-2' }]] },
				{ allObjects: [[{ id: 'obj-3' }]] },
			], 'obj-2')

			expect(location).toEqual({ docIndex: 0, pageIndex: 1 })
		})

		it('returns null when the object does not exist', () => {
			expect(findPdfObjectLocation([{ allObjects: [[{ id: 'obj-1' }]] }], 'missing')).toBeNull()
		})
	})

	describe('RULE: signer replacement is resolved without component state mutation', () => {
		it('returns the next signer and preserves current placement', () => {
			const result = resolvePdfEditorSignerChange({
				availableSigners: [
					{ signRequestId: 1, displayName: 'One' },
					{ signRequestId: 2, email: 'two@example.com' },
				],
				selectedSigner: { signRequestId: 2 },
				object: {
					id: 'obj-1',
					signer: {
						signRequestId: 1,
						element: { elementId: 99, coordinates: { page: 1, left: 10, top: 20 } },
					},
				},
				documents: [{ allObjects: [[{ id: 'obj-1' }]] }],
			})

			expect(result).toEqual({
				docIndex: 0,
				signer: {
					signRequestId: 2,
					email: 'two@example.com',
					displayName: '2',
					element: { elementId: 99, coordinates: { page: 1, left: 10, top: 20 } },
				},
			})
		})

		it('returns null when no target signer can be resolved', () => {
			const result = resolvePdfEditorSignerChange({
				availableSigners: [{ signRequestId: 1, displayName: 'One' }],
				selectedSigner: { signRequestId: 3 },
				object: { id: 'obj-1' },
				documents: [],
			})

			expect(result).toBeNull()
		})
	})

	describe('RULE: placement calculation is isolated from component orchestration', () => {
		it('uses left/top coordinates directly', () => {
			const placement = calculatePdfPlacement({
				signer: {
					element: {
						documentIndex: 1,
						coordinates: { page: 2, left: 10, top: 20, width: 30, height: 40 },
					},
				},
				defaultDocIndex: 0,
				pageHeight: 800,
			})

			expect(placement).toEqual({
				docIndex: 1,
				pageIndex: 1,
				x: 10,
				y: 20,
				width: 30,
				height: 40,
			})
		})

		it('converts PDF coordinate boxes into canvas placement', () => {
			const placement = calculatePdfPlacement({
				signer: {
					element: {
						coordinates: { page: 1, llx: 50, lly: 100, urx: 250, ury: 700 },
					},
				},
				defaultDocIndex: 0,
				pageHeight: 841.89,
			})

			expect(placement).toEqual({
				docIndex: 0,
				pageIndex: 0,
				x: 50,
				y: 141.89,
				width: 200,
				height: 600,
			})
		})

		it('returns null when page number is invalid', () => {
			expect(calculatePdfPlacement({ signer: { element: { coordinates: { left: 10, top: 10 } } }, defaultDocIndex: 0, pageHeight: 500 })).toBeNull()
		})
	})

	describe('RULE: object creation is deterministic except for id generation', () => {
		it('builds a signature object from signer and placement', () => {
			const signer = { email: 'ada@example.com' }
			const object = createPdfEditorObject({
				signer,
				placement: { docIndex: 0, pageIndex: 0, x: 10, y: 20, width: 30, height: 40 },
				objectId: 'obj-fixed',
			})

			expect(object).toEqual({
				id: 'obj-fixed',
				type: 'signature',
				signer,
				x: 10,
				y: 20,
				width: 30,
				height: 40,
			})
		})

		it('creates ids with the expected prefix and random suffix', () => {
			expect(createPdfObjectId()).toMatch(/^obj-\d+-[a-z0-9]{6}$/)
		})
	})
})