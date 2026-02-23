/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, it, expect } from 'vitest'
import { fetchDocuments } from '../../services/FilesService'

describe('FilesService', () => {
	it('fetchDocuments is exported as async function', () => {
		expect(fetchDocuments).toBeDefined()
		expect(typeof fetchDocuments).toBe('function')
	})

	it('fetchDocuments returns a promise', async () => {
		const result = fetchDocuments()
		expect(result).toBeInstanceOf(Promise)
		await result
	})
})
