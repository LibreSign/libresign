/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from '@nextcloud/axios'
import { generateOCSResponse } from '../test-helpers.js'

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path, params) => `/ocs/v2.php${path.replace(/{(\w+)}/g, (_, key) => params[key])}`),
}))

vi.mock('vue', () => ({
	del: vi.fn((obj, key) => { delete obj[key] }),
	set: vi.fn((obj, key, value) => { obj[key] = value }),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
	subscribe: vi.fn(),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({
		uid: 'testuser',
		displayName: 'Test User',
		email: 'test@example.com',
	})),
}))

vi.mock('@nextcloud/moment', () => ({
	default: vi.fn(() => ({ fromNow: () => '2 days ago' })),
}))

vi.mock('./filesSorting.js', () => ({
	useFilesSortingStore: vi.fn(() => ({ sortedFiles: [] })),
}))

vi.mock('./filters.js', () => ({
	useFiltersStore: vi.fn(() => ({ filters: {} })),
}))

vi.mock('./identificationDocument.js', () => ({
	useIdentificationDocumentStore: vi.fn(() => ({ documents: [] })),
}))

vi.mock('./sidebar.js', () => ({
	useSidebarStore: vi.fn(() => ({ hideSidebar: vi.fn() })),
}))

vi.mock('./sign.js', () => ({
	useSignStore: vi.fn(() => ({ signData: {} })),
}))

describe('files store - critical business rules', () => {
	let useFilesStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
		vi.resetModules()

		const module = await import('./files.js')
		useFilesStore = module.useFilesStore
	})

	describe('RULE: removing selected file clears selection', () => {
		it('removing selected file resets selectedFileId', () => {
			const store = useFilesStore()
			store.files[123] = { id: 123, name: 'test.pdf' }
			store.selectedFileId = 123

			store.removeFileById(123)

			expect(store.selectedFileId).toBe(0)
		})
	})

	describe('RULE: file settings are merged, not replaced', () => {
		it('updating a file preserves previous settings', async () => {
			const store = useFilesStore()
			store.files[123] = {
				id: 123,
				settings: { allowEdit: true, requireAuth: false },
			}

			await store.addFile({
				id: 123,
				signers: [],
				settings: { requireAuth: true, newSetting: 'value' },
			})

			expect(store.files[123].settings).toEqual({
				allowEdit: true,
				requireAuth: true,
				newSetting: 'value',
			})
		})
	})

	describe('RULE: envelope filesCount reflects file operations', () => {
		it('adding files increments envelope filesCount', async () => {
			const store = useFilesStore()
			store.selectedFileId = 100
			store.files[100] = { id: 100, filesCount: 2 }

			axios.post.mockResolvedValue(generateOCSResponse({
				payload: { filesCount: 5 },
			}))

			await store.addFilesToEnvelope('uuid', new FormData())

			expect(store.files[100].filesCount).toBe(5)
		})

		it('removing files decrements filesCount correctly', async () => {
			const store = useFilesStore()
			store.selectedFileId = 100
			store.files[100] = { id: 100, filesCount: 5 }

			axios.delete.mockResolvedValue({})

			await store.removeFilesFromEnvelope([1, 2, 3])

			expect(store.files[100].filesCount).toBe(2) // 5 - 3 = 2
		})

		it('filesCount never goes negative', async () => {
			const store = useFilesStore()
			store.selectedFileId = 100
			store.files[100] = { id: 100, filesCount: 1 }

			axios.delete.mockResolvedValue({})

			await store.removeFilesFromEnvelope([1, 2, 3, 4, 5])

			expect(store.files[100].filesCount).toBe(0) // Math.max(0, 1 - 5)
		})
	})

	describe('RULE: upload cancellation has special handling', () => {
		it('ERR_CANCELED returns a specific message', async () => {
			const store = useFilesStore()

			const error = new Error('Cancelled')
			error.code = 'ERR_CANCELED'
			axios.post.mockRejectedValue(error)

			const result = await store.addFilesToEnvelope('uuid', new FormData())

			expect(result.success).toBe(false)
			expect(result.message).toBe('Upload cancelled')
		})
	})

	describe('RULE: signing order affects signing permission', () => {
		it('in ordered flow, signer with higher order cannot sign', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				status: 1,
				signatureFlow: 'ordered_numeric',
				signers: [
					{ me: false, signingOrder: 1, signed: [] },
					{ me: true, signingOrder: 2, signed: [] },
				],
			}

			expect(store.canSign()).toBe(false)
		})

		it('in ordered flow, signer with lowest pending order can sign', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				status: 1,
				signatureFlow: 'ordered_numeric',
				signers: [
					{ me: false, signingOrder: 1, signed: ['signed'] },
					{ me: true, signingOrder: 2, signed: [] },
				],
			}

			expect(store.canSign()).toBe(true)
		})

		it('in parallel flow, order does not block signing', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				status: 1,
				signatureFlow: 'parallel',
				signers: [
					{ me: false, signingOrder: 1, signed: [] },
					{ me: true, signingOrder: 2, signed: [] },
				],
			}

			expect(store.canSign()).toBe(true)
		})
	})
})
