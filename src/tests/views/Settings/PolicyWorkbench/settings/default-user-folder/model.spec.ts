/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	DEFAULT_USER_FOLDER,
	isCustomDefaultUserFolder,
	normalizeDefaultUserFolder,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/default-user-folder/model'

describe('default-user-folder model', () => {
	it('exposes LibreSign as the default folder name', () => {
		expect(DEFAULT_USER_FOLDER).toBe('LibreSign')
	})

	it('normalizes custom folder names while trimming surrounding whitespace', () => {
		expect(normalizeDefaultUserFolder('  Customer Signatures  ')).toBe('Customer Signatures')
	})

	it('falls back to the default folder for blank or unsupported values', () => {
		expect(normalizeDefaultUserFolder('   ')).toBe(DEFAULT_USER_FOLDER)
		expect(normalizeDefaultUserFolder(null as never)).toBe(DEFAULT_USER_FOLDER)
		expect(normalizeDefaultUserFolder(false as never)).toBe(DEFAULT_USER_FOLDER)
	})

	it('detects whether the configured folder differs from the canonical default', () => {
		expect(isCustomDefaultUserFolder('Customer Signatures')).toBe(true)
		expect(isCustomDefaultUserFolder('  LibreSign  ')).toBe(false)
		expect(isCustomDefaultUserFolder('')).toBe(false)
	})
})
