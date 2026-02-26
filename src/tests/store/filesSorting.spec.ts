/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useFilesSortingStore } from '../../store/filesSorting.js'

const { emitMock, putMock } = vi.hoisted(() => ({
	emitMock: vi.fn(),
	putMock: vi.fn(() => Promise.resolve()),
}))

vi.mock('@nextcloud/event-bus', () => ({
	emit: emitMock,
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: () => ({ files_list_sorting_mode: 'name', files_list_sorting_direction: 'asc' }),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		put: putMock,
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (path: string, params: { key: string }) => path.replace('{key}', params.key),
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

		expect(store.sortingDirection).toBe('asc')
		expect(emitMock).toHaveBeenCalledWith('libresign:sorting:update')
	})

	it('changes sorting mode and resets direction', async () => {
		const store = useFilesSortingStore()

		await store.toggleSortBy('date')

		expect(store.sortingMode).toBe('date')
		expect(store.sortingDirection).toBe('asc')
		expect(emitMock).toHaveBeenCalledWith('libresign:sorting:update')
	})

	it('should toggle direction when sorting by the same key', async () => {
		const store = useFilesSortingStore()
		store.sortingMode = 'name'
		store.sortingDirection = 'asc'

		await store.toggleSortBy('name')

		expect(store.sortingMode).toBe('name')
		expect(store.sortingDirection).toBe('desc')
	})

	it('should toggle direction again when sorting by same key', async () => {
		const store = useFilesSortingStore()
		store.sortingMode = 'name'
		store.sortingDirection = 'desc'

		await store.toggleSortBy('name')

		expect(store.sortingMode).toBe('name')
		expect(store.sortingDirection).toBe('asc')
	})

	it('saves sorting settings to API', async () => {
		const store = useFilesSortingStore()

		await store.saveSorting()

		expect(putMock).toHaveBeenCalledTimes(2)
		expect(putMock).toHaveBeenCalledWith(
			'/apps/libresign/api/v1/account/config/files_list_sorting_mode',
			{ value: 'created_at' }
		)
		expect(putMock).toHaveBeenCalledWith(
			'/apps/libresign/api/v1/account/config/files_list_sorting_direction',
			{ value: 'desc' }
		)
	})

	it('handles save errors gracefully', async () => {
		const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
		const store = useFilesSortingStore()
		putMock.mockRejectedValueOnce(new Error('API Error'))

		await store.saveSorting()

		expect(consoleErrorSpy).toHaveBeenCalled()
		consoleErrorSpy.mockRestore()
	})

	it('maintains initial state from loadState', () => {
		const store = useFilesSortingStore()

		expect(store.sortingMode).toBe('created_at')
		expect(store.sortingDirection).toBe('desc')
	})

	it('emits event after toggling direction', async () => {
		const store = useFilesSortingStore()

		await store.toggleSortingDirection()

		expect(emitMock).toHaveBeenCalledWith('libresign:sorting:update')
	})

	it('emits event after toggling sort by', async () => {
		const store = useFilesSortingStore()

		await store.toggleSortBy('date')

		expect(emitMock).toHaveBeenCalledWith('libresign:sorting:update')
	})

	it('changes direction from desc to asc', async () => {
		const store = useFilesSortingStore()
		store.sortingDirection = 'desc'

		await store.toggleSortingDirection()

		expect(store.sortingDirection).toBe('asc')
	})
})
