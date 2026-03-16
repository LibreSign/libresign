/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	normalizeDocumentForVisibleElements,
	normalizeFileForVisibleElements,
	normalizeVisibleElementList,
	normalizeVisibleElementRecord,
} from '../../services/signingDocumentAdapter'

describe('signingDocumentAdapter', () => {
	it('normalizes visible element ids and coordinate numbers', () => {
		expect(normalizeVisibleElementRecord({
			elementId: '11',
			signRequestId: '22',
			fileId: '33',
			type: 'signature',
			coordinates: {
				page: '1',
				left: '10',
				top: '20',
				width: '30',
				height: '40',
			},
		})).toEqual({
			elementId: 11,
			signRequestId: 22,
			fileId: 33,
			type: 'signature',
			coordinates: {
				page: 1,
				left: 10,
				top: 20,
				width: 30,
				height: 40,
			},
		})
	})

	it('filters invalid visible elements from lists', () => {
		expect(normalizeVisibleElementList([
			{ elementId: 10, signRequestId: 20, fileId: 30, type: 'signature', coordinates: { page: 1 } },
			{ signRequestId: 20, fileId: 30, type: 'signature', coordinates: { page: 1 } },
		])).toEqual([
			{ elementId: 10, signRequestId: 20, fileId: 30, type: 'signature', coordinates: { page: 1 } },
		])
	})

	it('normalizes files for visible elements consumers', () => {
		expect(normalizeFileForVisibleElements({
			id: '10',
			name: 'child.pdf',
			metadata: { extension: 'pdf' },
			signers: [{ signRequestId: 501, displayName: 'Ada' }],
			visibleElements: [
				{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10 } },
				{ fileId: 10, signRequestId: 501, type: 'signature', coordinates: { page: 1 } },
			],
		})).toEqual({
			id: 10,
			name: 'child.pdf',
			metadata: { extension: 'pdf' },
			signers: [{ signRequestId: 501, displayName: 'Ada' }],
			visibleElements: [
				{ elementId: 201, fileId: 10, signRequestId: 501, type: 'signature', coordinates: { page: 1, left: 10 } },
			],
			files: undefined,
		})
	})

	it('normalizes documents for visible elements consumers', () => {
		expect(normalizeDocumentForVisibleElements({
			id: 1,
			uuid: 'uuid-123',
			name: 'Envelope',
			status: 5,
			statusText: 'Signing',
			settings: { path: '/Contracts' },
			visibleElements: [
				{ elementId: '7', signRequestId: '8', fileId: '9', type: 'signature', coordinates: { page: '1' } },
			],
			files: [
				{ id: '10', name: 'child.pdf', visibleElements: [] },
			],
		})).toEqual({
			id: 1,
			uuid: 'uuid-123',
			name: 'Envelope',
			status: 5,
			statusText: 'Signing',
			metadata: undefined,
			settings: { path: '/Contracts' },
			signers: [],
			visibleElements: [
				{ elementId: 7, signRequestId: 8, fileId: 9, type: 'signature', coordinates: { page: 1 } },
			],
			files: [
				{ id: 10, name: 'child.pdf', metadata: undefined, signers: [], visibleElements: [], files: undefined },
			],
		})
	})
})
