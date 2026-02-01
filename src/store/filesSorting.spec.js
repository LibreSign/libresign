/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useFilesSortingStore } from './filesSorting.js'

const emitMock = vi.fn()
const putMock = vi.fn(() => Promise.resolve())

vi.mock('@nextcloud/event-bus', () => ({
	emit: emitMock,
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: () => ({ sorting_mode: 'name', sorting_direction: 'asc' }),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		put: putMock,
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (path, params) => path.replace('{key}', params.key),
}))

describe('filesSorting store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		emitMock.mockClear()
		putMock.mockClear()
	})

	it('toggles sorting direction', async () => {
		const store = useFilesSortingStore()

		await store.toggleSortingDirection()

		expect(store.sortingDirection).toBe('desc')
		expect(emitMock).toHaveBeenCalledWith('libresign:sorting:update')
	})

	it('changes sorting mode and resets direction', async () => {
		const store = useFilesSortingStore()

		await store.toggleSortBy('date')

		expect(store.sortingMode).toBe('date')
		expect(store.sortingDirection).toBe('asc')
		expect(emitMock).toHaveBeenCalledWith('libresign:sorting:update')
	})
})
