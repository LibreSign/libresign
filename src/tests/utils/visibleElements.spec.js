/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import {
	aggregateVisibleElementsByFiles,
	findFileById,
	getFileSigners,
	getFileUrl,
	getVisibleElementsFromDocument,
	getVisibleElementsFromFile,
	idsMatch,
} from '../../services/visibleElementsService.js'

describe('visibleElements utils', () => {
	it('collects visible elements from top-level and signer-level document payload', () => {
		const document = {
			visibleElements: [{ elementId: 1, fileId: 10, signRequestId: 100 }],
			signers: [
				{ signRequestId: 100, visibleElements: [{ elementId: 2, fileId: 11, signRequestId: 101 }] },
			],
		}

		expect(getVisibleElementsFromDocument(document)).toEqual([
			{ elementId: 1, fileId: 10, signRequestId: 100 },
			{ elementId: 2, fileId: 11, signRequestId: 101 },
		])
	})

	it('returns only top-level visible elements when signers key is absent', () => {
		const document = {
			visibleElements: [{ elementId: 10, fileId: 20, signRequestId: 30 }],
		}

		expect(getVisibleElementsFromDocument(document)).toEqual([
			{ elementId: 10, fileId: 20, signRequestId: 30 },
		])
	})

	it('collects visible elements from file signer payload when top-level is empty', () => {
		const file = {
			id: 1,
			visibleElements: [],
			signers: [
				{ visibleElements: [{ elementId: 100, fileId: 1, signRequestId: 200 }] },
			],
		}

		expect(getVisibleElementsFromFile(file)).toEqual([
			{ elementId: 100, fileId: 1, signRequestId: 200 },
		])
	})

	it('aggregates visible elements across files with nested signers', () => {
		const files = [
			{ id: 545, signers: [{ visibleElements: [{ elementId: 185, fileId: 545, signRequestId: 603 }] }] },
			{ id: 546, signers: [{ visibleElements: [{ elementId: 186, fileId: 546, signRequestId: 604 }] }] },
		]

		expect(aggregateVisibleElementsByFiles(files)).toEqual([
			{ elementId: 185, fileId: 545, signRequestId: 603 },
			{ elementId: 186, fileId: 546, signRequestId: 604 },
		])
	})

	it('gets file URL from direct and nested payload formats', () => {
		expect(getFileUrl({ file: '/apps/libresign/p/pdf/a' })).toBe('/apps/libresign/p/pdf/a')
		expect(getFileUrl({ files: [{ file: '/apps/libresign/p/pdf/b' }] })).toBe('/apps/libresign/p/pdf/b')
		expect(getFileUrl({})).toBe(null)
	})

	it('gets signers from direct and nested payload formats', () => {
		expect(getFileSigners({ signers: [{ signRequestId: 1 }] })).toEqual([{ signRequestId: 1 }])
		expect(getFileSigners({ files: [{ signers: [{ signRequestId: 2 }] }] })).toEqual([{ signRequestId: 2 }])
		expect(getFileSigners({})).toEqual([])
	})

	it('matches identifiers across number/string payload variations', () => {
		expect(idsMatch(545, '545')).toBe(true)
		expect(idsMatch('603', 603)).toBe(true)
		expect(idsMatch(545, 546)).toBe(false)
	})

	it('finds file by id with strict payload variations', () => {
		const files = [{ id: 545, name: 'one' }, { id: '546', name: 'two' }]

		expect(findFileById(files, '545')?.name).toBe('one')
		expect(findFileById(files, 546)?.name).toBe('two')
		expect(findFileById(files, 999)).toBe(null)
	})
})
