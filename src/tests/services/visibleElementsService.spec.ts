/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import {
	getVisibleElementsFromDocument,
} from '../../services/visibleElementsService'

describe('visibleElementsService', () => {
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
})
