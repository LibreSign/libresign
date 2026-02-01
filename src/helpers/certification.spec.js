/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

const optionFromMock = vi.fn((value) => ({ value }))

vi.mock('@marionebl/option', () => ({
	Option: {
		from: optionFromMock,
	},
}))

const loadModule = async () => {
	vi.resetModules()
	optionFromMock.mockClear()
	return await import('./certification.js')
}

describe('selectCustonOption', () => {
	it('returns option wrapped when id exists', async () => {
		const { selectCustonOption, options } = await loadModule()
		const expectedOption = options.find((item) => item.id === 'CN')

		const result = selectCustonOption('CN')

		expect(optionFromMock).toHaveBeenCalledWith(expectedOption)
		expect(result).toEqual({ value: expectedOption })
	})

	it('returns empty option when id does not exist', async () => {
		const { selectCustonOption } = await loadModule()

		const result = selectCustonOption('UNKNOWN')

		expect(optionFromMock).toHaveBeenCalledWith(undefined)
		expect(result).toEqual({ value: undefined })
	})
})
