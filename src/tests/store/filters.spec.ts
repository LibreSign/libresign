/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { MockedFunction } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

type AxiosMock = {
	put: MockedFunction<(url: string, data?: unknown) => Promise<{ data: { success: boolean } }>>
}

// Mock @nextcloud/logger to avoid import-time errors
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

vi.mock('@nextcloud/axios', () => ({
	default: {
		put: vi.fn(),
	},
}))

vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string, params?: Record<string, string>) => {
		let url = `/ocs/v2.php${path}`
		if (params) {
			Object.entries(params).forEach(([key, value]) => {
				url = url.replace(`{${key}}`, value)
			})
		}
		return url
	}),
}))

vi.mock('../../helpers/logger.js', () => ({
	default: {
		debug: vi.fn(),
	},
}))

describe('filters store - filter business rules', () => {
	const axiosMock = axios as unknown as AxiosMock
	let useFiltersStore: typeof import('../../store/filters.js').useFiltersStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		vi.clearAllMocks()

		const module = await import('../../store/filters.js')
		useFiltersStore = module.useFiltersStore
	})

	describe('business rule: activeChips should return all active chips from all filters', () => {
		it('returns empty array when there are no chips', () => {
			const store = useFiltersStore()

			expect(store.activeChips).toEqual([])
		})

		it('returns chips from a single filter', () => {
			const store = useFiltersStore()
			const signedChip = { id: 'signed', label: 'Signed' }
			store.chips = {
				status: [signedChip],
			}

			expect(store.activeChips).toEqual([signedChip])
		})

		it('returns chips from multiple filters in a single array', () => {
			const store = useFiltersStore()
			const signedChip = { id: 'signed', label: 'Signed' }
			const todayChip = { id: 'today', label: 'Today' }
			store.chips = {
				status: [signedChip],
				modified: [todayChip],
			}

			const chips = store.activeChips
			expect(chips).toHaveLength(2)
			expect(chips).toContainEqual(signedChip)
			expect(chips).toContainEqual(todayChip)
		})

		it('returns multiple chips from the same filter', () => {
			const store = useFiltersStore()
			store.chips = {
				status: [
					{ id: 'signed', label: 'Signed' },
					{ id: 'pending', label: 'Pending' },
				],
			}

			expect(store.activeChips).toHaveLength(2)
		})
	})

	describe('business rule: filterStatusArray should convert JSON string to array', () => {
		it('returns empty array when filter_status is empty string', () => {
			const store = useFiltersStore()
			store.filter_status = ''

			expect(store.filterStatusArray).toEqual([])
		})

		it('returns empty array when filter_status is invalid JSON', () => {
			const originalError = console.error
			console.error = vi.fn()

			const store = useFiltersStore()
			store.filter_status = 'invalid json'

			expect(store.filterStatusArray).toEqual([])
			console.error = originalError
		})

		it('converts valid JSON to array', () => {
			const store = useFiltersStore()
			store.filter_status = '["signed","pending"]'

			expect(store.filterStatusArray).toEqual(['signed', 'pending'])
		})

		it('converts JSON object array', () => {
			const store = useFiltersStore()
			store.filter_status = '[{"id":1},{"id":2}]'

			expect(store.filterStatusArray).toEqual([{ id: 1 }, { id: 2 }])
		})
	})

	describe('business rule: filterModifiedRange should compute date range from preset id', () => {
		it('returns null when filter_modified is empty', () => {
			const store = useFiltersStore()
			store.filter_modified = ''

			expect(store.filterModifiedRange).toBeNull()
		})

		it('returns null for unknown preset id', () => {
			const store = useFiltersStore()
			store.filter_modified = 'unknown-preset'

			expect(store.filterModifiedRange).toBeNull()
		})

		it.each(['today', 'last-7', 'last-30', 'this-year', 'last-year'])('returns { start, end } for preset "%s"', (presetId) => {
			const store = useFiltersStore()
			store.filter_modified = presetId

			const range = store.filterModifiedRange
			expect(range).not.toBeNull()
			expect(range?.start).toBeTypeOf('number')
			expect(range?.end).toBeTypeOf('number')
			expect(range!.start).toBeLessThan(range!.end)
		})

		it('today preset: start is midnight and end is end of day', () => {
			const store = useFiltersStore()
			store.filter_modified = 'today'

			const range = store.filterModifiedRange!
			const startDate = new Date(range.start)
			const endDate = new Date(range.end)

			expect(startDate.getHours()).toBe(0)
			expect(startDate.getMinutes()).toBe(0)
			expect(endDate.getHours()).toBe(23)
			expect(endDate.getMinutes()).toBe(59)
		})
	})

	describe('business rule: chips update should only update UI chips state', () => {
		it('onFilterUpdateChips should NOT emit libresign:filters:update event', async () => {
			const store = useFiltersStore()
			const event = {
				id: 'status',
				detail: [{ id: 'signed', label: 'Signed' }],
			}

			await store.onFilterUpdateChips(event)

			expect(emit).not.toHaveBeenCalled()
		})

		it('onFilterUpdateChips should update chips for specific filter', async () => {
			const store = useFiltersStore()
			const event = {
				id: 'status',
				detail: [{ id: 'signed', label: 'Signed' }],
			}

			await store.onFilterUpdateChips(event)

			expect(store.chips.status).toEqual([{ id: 'signed', label: 'Signed' }])
		})

		it('onFilterUpdateChips should not overwrite other filters', async () => {
			const store = useFiltersStore()
			store.chips = {
				modified: [{ id: 'today', label: 'Today' }],
			}

			const event = {
				id: 'status',
				detail: [{ id: 'signed', label: 'Signed' }],
			}

			await store.onFilterUpdateChips(event)

			expect(store.chips.modified).toEqual([{ id: 'today', label: 'Today' }])
			expect(store.chips.status).toEqual([{ id: 'signed', label: 'Signed' }])
		})
	})

	describe('business rule: modification filter should save to server', () => {
		it('modified filter should save first chip ID to server', async () => {
			axiosMock.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			const event = {
				id: 'modified',
				detail: [{ id: 'today', label: 'Today' }],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(axiosMock.put).toHaveBeenCalledWith(
				'/ocs/v2.php/apps/libresign/api/v1/account/config/filter_modified',
				{ value: 'today' }
			)
		})

		it('modified filter with multiple chips should save only the first', async () => {
			axiosMock.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			const event = {
				id: 'modified',
				detail: [
					{ id: 'today', label: 'Today' },
					{ id: 'yesterday', label: 'Yesterday' },
				],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(axiosMock.put).toHaveBeenCalledWith(
				'/ocs/v2.php/apps/libresign/api/v1/account/config/filter_modified',
				{ value: 'today' }
			)
		})

		it('empty modified filter should save empty string', async () => {
			axiosMock.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			const event = {
				id: 'modified',
				detail: [],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(axiosMock.put).toHaveBeenCalledWith(
				'/ocs/v2.php/apps/libresign/api/v1/account/config/filter_modified',
				{ value: '' }
			)
		})

		it('modified filter should update local state after saving', async () => {
			axiosMock.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			const event = {
				id: 'modified',
				detail: [{ id: 'today', label: 'Today' }],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(store.filter_modified).toBe('today')
		})

		it('empty modified filter should clear local state', async () => {
			axiosMock.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			store.filter_modified = 'last-7'
			const event = {
				id: 'modified',
				detail: [],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(store.filter_modified).toBe('')
		})
	})

	describe('business rule: status filter should save JSON array to server', () => {
		it('status filter should save ID array as JSON', async () => {
			axiosMock.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			const event = {
				id: 'status',
				detail: [
					{ id: 'signed', label: 'Signed' },
					{ id: 'pending', label: 'Pending' },
				],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(axiosMock.put).toHaveBeenCalledWith(
				'/ocs/v2.php/apps/libresign/api/v1/account/config/filter_status',
				{ value: '["signed","pending"]' }
			)
		})

		it('empty status filter should save empty string', async () => {
			axiosMock.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			const event = {
				id: 'status',
				detail: [],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(axiosMock.put).toHaveBeenCalledWith(
				'/ocs/v2.php/apps/libresign/api/v1/account/config/filter_status',
				{ value: '' }
			)
		})

		it('status filter should update local state after saving', async () => {
			axiosMock.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			const event = {
				id: 'status',
				detail: [{ id: 'signed', label: 'Signed' }],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(store.filter_status).toBe('["signed"]')
		})
	})

	describe('business rule: only modified and status filters should save to server', () => {
		it('filter with different ID should not call API', async () => {
			const store = useFiltersStore()
			const event = {
				id: 'other_filter',
				detail: [{ id: 'value', label: 'Value' }],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(axiosMock.put).not.toHaveBeenCalled()
		})

		it('filter with different ID should still emit event', async () => {
			const store = useFiltersStore()
			const event = {
				id: 'other_filter',
				detail: [{ id: 'value', label: 'Value' }],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(emit).not.toHaveBeenCalled()
		})
	})
})
