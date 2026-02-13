/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, it, expect, vi } from 'vitest'

// Mock @nextcloud packages before any other imports
vi.mock('@nextcloud/logger', () => ({
	getLogger: vi.fn(() => ({
		error: vi.fn(),
		warn: vi.fn(),
		info: vi.fn(),
		debug: vi.fn(),
	})),
	getLoggerBuilder: vi.fn(() => ({
		setApp: vi.fn().mockReturnThis(),
		detectUser: vi.fn().mockReturnThis(),
		build: vi.fn(() => ({
			error: vi.fn(),
			warn: vi.fn(),
			info: vi.fn(),
			debug: vi.fn(),
		})),
	})),
}))

const mockVue = {
	use: vi.fn(),
}

vi.mock('vue', async () => {
	const actual = await vi.importActual('vue')
	const Vue = actual.default ?? actual
	return {
		...actual,
		default: Object.assign(Vue, {
			use: mockVue.use,
		}),
	}
})

vi.mock('vuelidate', () => ({
	default: {},
}))

describe('vuelidate plugin', () => {
	it('registers Vuelidate plugin with Vue', async () => {
		vi.resetModules()
		await import('../../plugins/vuelidate.js')

		expect(mockVue.use).toHaveBeenCalled()
	})
})
