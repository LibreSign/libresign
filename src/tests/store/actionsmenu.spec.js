/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useActionsMenuStore } from '../../store/actionsmenu.js'

describe('actionsMenu store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('initializes with null opened state', () => {
		const store = useActionsMenuStore()

		expect(store.opened).toBe(null)
	})

	it('can update opened state', () => {
		const store = useActionsMenuStore()

		store.opened = 'file-123'

		expect(store.opened).toBe('file-123')
	})

	it('can clear opened state', () => {
		const store = useActionsMenuStore()
		store.opened = 'file-456'

		store.opened = null

		expect(store.opened).toBe(null)
	})
})
