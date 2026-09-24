/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import {
	getCurrentUserSignRequestIds,
	getFileUrl,
	getSignersWithoutVisibleSignatureElements,
	getVisibleElementsFromDocument,
	hasVisibleElementsForCurrentUser,
} from '../../services/visibleElementsService'

describe('visibleElementsService', () => {
	describe('getFileUrl', () => {
		it('supports nested file objects with url payloads', () => {
			const file = {
				file: {
					url: '/apps/libresign/p/pdf/uuid-123',
				},
			}

			expect(getFileUrl(file)).toBe('/apps/libresign/p/pdf/uuid-123')
		})

		it('finds the first renderable child file recursively', () => {
			const file = {
				files: [
					{ id: 10, file: null },
					{ id: 20, file: { url: '/apps/libresign/p/pdf/uuid-456' } },
				],
			}

			expect(getFileUrl(file)).toBe('/apps/libresign/p/pdf/uuid-456')
		})
	})

	describe('getVisibleElementsFromDocument', () => {
		it('includes visible elements from child files (envelope)', () => {
			const document = {
				visibleElements: [],
				signers: [],
				files: [
					{
						id: 10,
						visibleElements: [
							{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10, top: 20 } },
						],
						signers: [
							{
								description: null,
								displayName: 'Signer 1',
								request_sign_date: '2026-03-10 10:00:00',
								signed: null,
								me: false,
								signRequestId: 501,
								status: 1,
								statusText: 'Able to sign',
								email: 'signer1@example.com',
								visibleElements: [
									{ elementId: 202, fileId: 10, signRequestId: 501, type: 'initials', coordinates: { page: 1, left: 30, top: 40 } },
								],
							},
						],
					},
					{
						id: 11,
						visibleElements: [
							{ elementId: 203, fileId: 11, signRequestId: 502, type: 'signature', coordinates: { page: 2, left: 50, top: 60 } },
						],
					},
				],
			}

			const result = getVisibleElementsFromDocument(document)

			expect(result).toEqual([
				{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10, top: 20 } },
				{ elementId: 202, fileId: 10, signRequestId: 501, type: 'initials', coordinates: { page: 1, left: 30, top: 40 } },
				{ elementId: 203, fileId: 11, signRequestId: 502, type: 'signature', coordinates: { page: 2, left: 50, top: 60 } },
			])
		})

		it('keeps top-level visible elements', () => {
			const document = {
				visibleElements: [
					{ elementId: 101, fileId: 1, signRequestId: 401, type: 'signature', coordinates: { page: 1, left: 12, top: 18 } },
				],
				signers: [],
				files: [],
			}

			const result = getVisibleElementsFromDocument(document)

			expect(result).toEqual([
				{ elementId: 101, fileId: 1, signRequestId: 401, type: 'signature', coordinates: { page: 1, left: 12, top: 18 } },
			])
		})
	})

	describe('getCurrentUserSignRequestIds', () => {
		it('collects current signer ids from envelope parent and child files', () => {
			const document = {
				signers: [
					{ me: true, signRequestId: 700 },
				],
				files: [
					{
						id: 10,
						signers: [
							{ me: true, signRequestId: 501 },
							{ me: false, signRequestId: 502 },
						],
					},
				],
			}

			const result = getCurrentUserSignRequestIds(document)

			expect(result).toEqual([700, 501])
		})
	})

	describe('hasVisibleElementsForCurrentUser', () => {
		it('detects child file visible elements for current envelope signer', () => {
			const document = {
				visibleElements: [],
				signers: [
					{ me: true, signRequestId: 700 },
				],
				files: [
					{
						id: 10,
						visibleElements: [
							{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10, top: 20 } },
						],
						signers: [
							{ me: true, signRequestId: 501 },
						],
					},
				],
			}

			expect(hasVisibleElementsForCurrentUser(document)).toBe(true)
		})
	})

	describe('getSignersWithoutVisibleSignatureElements', () => {
		it('returns empty array when all signing participants have visible signature fields', () => {
			const document = {
				visibleElements: [
					{ elementId: 1, fileId: 10, signRequestId: 101, type: 'signature', coordinates: { page: 1, left: 10, top: 10 } },
					{ elementId: 2, fileId: 10, signRequestId: 102, type: 'signature', coordinates: { page: 1, left: 20, top: 20 } },
				],
			}
			const signers = [
				{ displayName: 'Alice', signRequestId: 101, participantRole: 'signer' },
				{ displayName: 'Bob', signRequestId: 102, participantRole: 'signer' },
			]

			const result = getSignersWithoutVisibleSignatureElements(document, signers)

			expect(result).toEqual([])
		})

		it('returns all signers when no visible elements exist', () => {
			const document = {
				visibleElements: [],
			}
			const signers = [
				{ displayName: 'Alice', signRequestId: 101, participantRole: 'signer' },
				{ displayName: 'Bob', signRequestId: 102, participantRole: 'signer' },
			]

			const result = getSignersWithoutVisibleSignatureElements(document, signers)

			expect(result).toEqual(signers)
		})

		it('returns only affected signers when some signers have visible signatures and some do not', () => {
			const document = {
				visibleElements: [
					{ elementId: 1, fileId: 10, signRequestId: 101, type: 'signature', coordinates: { page: 1, left: 10, top: 10 } },
					{ elementId: 2, fileId: 10, signRequestId: 103, type: 'signature', coordinates: { page: 1, left: 30, top: 30 } },
					{ elementId: 3, fileId: 10, signRequestId: 103, type: 'signature', coordinates: { page: 2, left: 40, top: 40 } },
				],
			}
			const alice = { displayName: 'Alice', signRequestId: 101, participantRole: 'signer' }
			const bob = { displayName: 'Bob', signRequestId: 102, participantRole: 'signer' }
			const carol = { displayName: 'Carol', signRequestId: 103, participantRole: 'signer' }

			const result = getSignersWithoutVisibleSignatureElements(document, [alice, bob, carol])

			expect(result).toEqual([bob])
		})

		it('matches strictly by signRequestId and not by name or email', () => {
			const document = {
				visibleElements: [
					// Element belongs to signRequestId 999
					{ elementId: 1, fileId: 10, signRequestId: 999, type: 'signature', coordinates: { page: 1, left: 10, top: 10 } },
				],
			}
			const signer = { displayName: 'Signer with different id', signRequestId: 101, participantRole: 'signer' }

			const result = getSignersWithoutVisibleSignatureElements(document, [signer])

			expect(result).toEqual([signer])
		})

		it('treats text, date, and initials elements as not counting as visible signature fields', () => {
			const document = {
				visibleElements: [
					{ elementId: 1, fileId: 10, signRequestId: 101, type: 'text', coordinates: { page: 1, left: 10, top: 10 } },
					{ elementId: 2, fileId: 10, signRequestId: 101, type: 'date', coordinates: { page: 1, left: 20, top: 20 } },
					{ elementId: 3, fileId: 10, signRequestId: 101, type: 'initials', coordinates: { page: 1, left: 30, top: 30 } },
				],
			}
			const signer = { displayName: 'Signer with text and date only', signRequestId: 101, participantRole: 'signer' }

			const result = getSignersWithoutVisibleSignatureElements(document, [signer])

			expect(result).toEqual([signer])
		})

		it('ignores observer participants even if they lack visible signature fields', () => {
			const document = {
				visibleElements: [
					{ elementId: 1, fileId: 10, signRequestId: 101, type: 'signature', coordinates: { page: 1, left: 10, top: 10 } },
				],
			}
			const signer = { displayName: 'Alice', signRequestId: 101, participantRole: 'signer' }
			const observer = { displayName: 'Dave (Observer)', signRequestId: 102, participantRole: 'observer' }

			const result = getSignersWithoutVisibleSignatureElements(document, [signer, observer])

			expect(result).toEqual([])
		})

		it('works with visible elements nested inside envelope child files', () => {
			const document = {
				visibleElements: [],
				files: [
					{
						id: 10,
						visibleElements: [
							{ elementId: 1, fileId: 10, signRequestId: 101, type: 'signature', coordinates: { page: 1, left: 10, top: 10 } },
						],
					},
					{
						id: 20,
						visibleElements: [
							{ elementId: 2, fileId: 20, signRequestId: 102, type: 'signature', coordinates: { page: 1, left: 20, top: 20 } },
						],
					},
				],
			}
			const alice = { displayName: 'Alice', signRequestId: 101, participantRole: 'signer' }
			const bob = { displayName: 'Bob', signRequestId: 102, participantRole: 'signer' }
			const charlie = { displayName: 'Charlie', signRequestId: 103, participantRole: 'signer' }

			const result = getSignersWithoutVisibleSignatureElements(document, [alice, bob, charlie])

			expect(result).toEqual([charlie])
		})

		it('handles null, undefined, or empty signers and documents gracefully', () => {
			expect(getSignersWithoutVisibleSignatureElements(null, null)).toEqual([])
			expect(getSignersWithoutVisibleSignatureElements(undefined, [])).toEqual([])
			expect(getSignersWithoutVisibleSignatureElements({ visibleElements: [] }, null)).toEqual([])
			expect(getSignersWithoutVisibleSignatureElements(null, [{ displayName: 'Alice', signRequestId: 1 }])).toEqual([
				{ displayName: 'Alice', signRequestId: 1 },
			])
		})
	})
})
