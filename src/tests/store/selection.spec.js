/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useSelectionStore } from '../../store/selection.js'

vi.mock('@nextcloud/event-bus', () => ({
	subscribe: vi.fn(),
}))

describe('selection store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('deduplicates selected items', () => {
		const store = useSelectionStore()

		store.set([1, 2, 2, 3])

		expect(store.selected).toEqual([1, 2, 3])
	})

	it('tracks last selection when index is provided', () => {
		const store = useSelectionStore()

		store.set([10, 20])
		store.setLastIndex(1)

		expect(store.lastSelectedIndex).toBe(1)
		expect(store.lastSelection).toEqual([10, 20])
	})

	it('clears last selection when index is null', () => {
		const store = useSelectionStore()

		store.set([10, 20])
		store.setLastIndex(null)

		expect(store.lastSelectedIndex).toBeNull()
		expect(store.lastSelection).toEqual([])
	})

	it('resets selection state', () => {
		const store = useSelectionStore()

		store.set([1])
		store.setLastIndex(0)
		store.reset()

		expect(store.selected).toEqual([])
		expect(store.lastSelection).toEqual([])
		expect(store.lastSelectedIndex).toBeNull()
	})
})
