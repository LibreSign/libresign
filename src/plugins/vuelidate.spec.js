/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, it, expect, vi } from 'vitest'

const mockVue = {
	use: vi.fn(),
}

vi.mock('vue', () => ({
	default: mockVue,
}))

vi.mock('vuelidate', () => ({
	default: {},
}))

describe('vuelidate plugin', () => {
	it('registers Vuelidate plugin with Vue', async () => {
		await import('./vuelidate.js')

		expect(mockVue.use).toHaveBeenCalled()
	})
})
