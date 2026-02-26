/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import { SignFlowHandler } from '../../services/SignFlowHandler'

const createStore = () => ({
	showModal: vi.fn(),
	closeModal: vi.fn(),
})

describe('SignFlowHandler', () => {
	afterEach(() => {
		vi.restoreAllMocks()
	})
	it('shows modal for createSignature action', () => {
		const store = createStore()
		const handler = new SignFlowHandler(store)

		const result = handler.handleAction('createSignature')

		expect(store.showModal).toHaveBeenCalledWith('createSignature')
		expect(result).toBe('modalShown')
	})

	it('returns ready when sign has no unmet requirements', () => {
		const store = createStore()
		const handler = new SignFlowHandler(store)

		const result = handler.handleAction('sign', { unmetRequirement: undefined })

		expect(result).toBe('ready')
	})

	it('maps unmet requirement to modal and shows it', () => {
		const store = createStore()
		const handler = new SignFlowHandler(store)

		const result = handler.handleAction('sign', { unmetRequirement: 'emailCode' })

		expect(store.showModal).toHaveBeenCalledWith('emailToken')
		expect(result).toBe('modalShown')
	})

	it('returns null for unknown action', () => {
		const store = createStore()
		const handler = new SignFlowHandler(store)
		vi.spyOn(console, 'warn').mockImplementation(() => {})

		const result = handler.handleAction('unknown')

		expect(result).toBeNull()
	})

})
