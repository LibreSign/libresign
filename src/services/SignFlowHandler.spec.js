/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { SignFlowHandler } from './SignFlowHandler.js'

const createStore = () => ({
	showModal: vi.fn(),
	closeModal: vi.fn(),
})

describe('SignFlowHandler', () => {
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

		const result = handler.handleAction('sign', { unmetRequirement: null })

		expect(result).toBe('ready')
	})

	it('maps unmet requirement to modal and shows it', () => {
		const store = createStore()
		const handler = new SignFlowHandler(store)

		const result = handler.handleAction('sign', { unmetRequirement: 'emailCode' })

		expect(store.showModal).toHaveBeenCalledWith('emailToken')
		expect(result).toBe('modalShown')
	})

})
