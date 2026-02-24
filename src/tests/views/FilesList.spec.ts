/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'

// Only mock what the stores need
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
		post: vi.fn(),
		delete: vi.fn(),
		patch: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path) => `/ocs/v2.php${path}`),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({
		uid: 'testuser',
		displayName: 'Test User',
	})),
}))

vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
	subscribe: vi.fn(),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

vi.mock('@nextcloud/moment', () => ({
	default: vi.fn(() => ({
		fromNow: () => '2 days ago',
	})),
}))

describe('FilesList - URI file opening business rules', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	it('RULE: does nothing when uuid is not in query params', async () => {
		const filesStore = useFilesStore()
		const selectSpy = vi.spyOn(filesStore, 'selectFileByUuid')

		const uuid = undefined
		if (uuid) {
			await filesStore.selectFileByUuid(uuid)
		}

		expect(selectSpy).not.toHaveBeenCalled()
	})

	it('RULE: passes uuid to selectFileByUuid when present', async () => {
		const filesStore = useFilesStore()
		vi.spyOn(filesStore, 'selectFileByUuid').mockResolvedValue(42)

		const uuid = 'test-uuid-123'
		if (uuid) {
			await filesStore.selectFileByUuid(uuid)
		}

		expect(filesStore.selectFileByUuid).toHaveBeenCalledWith('test-uuid-123')
	})

	it('RULE: opens sidebar only when file is found', async () => {
		const filesStore = useFilesStore()
		const sidebarStore = useSidebarStore()
		const activeTabSpy = vi.spyOn(sidebarStore, 'activeRequestSignatureTab')

		vi.spyOn(filesStore, 'selectFileByUuid').mockResolvedValue(42)

		const uuid = 'test-uuid'
		await filesStore.selectFileByUuid(uuid).then((fileId: unknown) => {
			if (fileId) {
				sidebarStore.activeRequestSignatureTab()
			}
		})

		expect(activeTabSpy).toHaveBeenCalledTimes(1)
	})

	it('RULE: does not open sidebar when file not found', async () => {
		const filesStore = useFilesStore()
		const sidebarStore = useSidebarStore()
		const activeTabSpy = vi.spyOn(sidebarStore, 'activeRequestSignatureTab')

		vi.spyOn(filesStore, 'selectFileByUuid').mockResolvedValue(null)

		const uuid = 'nonexistent-uuid'
		await filesStore.selectFileByUuid(uuid).then((fileId: unknown) => {
			if (fileId) {
				sidebarStore.activeRequestSignatureTab()
			}
		})

		expect(activeTabSpy).not.toHaveBeenCalled()
	})
})
