/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import {
	getVisibleElementsFromDocument,
} from '../../services/visibleElementsService.js'

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
							{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature' },
						],
						signers: [
							{
								signRequestId: 501,
								visibleElements: [
									{ elementId: 202, fileId: 10, signRequestId: 501, type: 'initials' },
								],
							},
						],
					},
					{
						id: 11,
						visibleElements: [
							{ elementId: 203, fileId: 11, signRequestId: 502, type: 'signature' },
						],
					},
				],
			}

			const result = getVisibleElementsFromDocument(document)

			expect(result).toEqual([
				{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature' },
				{ elementId: 202, fileId: 10, signRequestId: 501, type: 'initials' },
				{ elementId: 203, fileId: 11, signRequestId: 502, type: 'signature' },
			])
		})

		it('keeps top-level visible elements', () => {
			const document = {
				visibleElements: [
					{ elementId: 101, fileId: 1, signRequestId: 401, type: 'signature' },
				],
				signers: [],
				files: [],
			}

			const result = getVisibleElementsFromDocument(document)

			expect(result).toEqual([
				{ elementId: 101, fileId: 1, signRequestId: 401, type: 'signature' },
			])
		})
	})
})
