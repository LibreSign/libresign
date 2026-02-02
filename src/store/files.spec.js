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

describe('files store - regras críticas de negócio', () => {
	let useFilesStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
		vi.resetModules()

		const module = await import('./files.js')
		useFilesStore = module.useFilesStore
	})

	describe('REGRA: remover arquivo selecionado deve limpar seleção', () => {
		it('arquivo selecionado removido zera selectedFileId', () => {
			const store = useFilesStore()
			store.files[123] = { id: 123, name: 'test.pdf' }
			store.selectedFileId = 123

			store.removeFileById(123)

			expect(store.selectedFileId).toBe(0)
		})
	})

	describe('REGRA: settings de arquivos devem ser mesclados, não substituídos', () => {
		it('atualizar arquivo preserva settings anteriores', async () => {
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

	describe('REGRA: envelope filesCount deve refletir operações de arquivo', () => {
		it('adicionar arquivos incrementa filesCount do envelope', async () => {
			const store = useFilesStore()
			store.selectedFileId = 100
			store.files[100] = { id: 100, filesCount: 2 }

			axios.post.mockResolvedValue(generateOCSResponse({
				payload: { filesCount: 5 },
			}))

			await store.addFilesToEnvelope('uuid', new FormData())

			expect(store.files[100].filesCount).toBe(5)
		})

		it('remover arquivos decrementa filesCount corretamente', async () => {
			const store = useFilesStore()
			store.selectedFileId = 100
			store.files[100] = { id: 100, filesCount: 5 }

			axios.delete.mockResolvedValue({})

			await store.removeFilesFromEnvelope([1, 2, 3])

			expect(store.files[100].filesCount).toBe(2) // 5 - 3 = 2
		})

		it('filesCount nunca fica negativo', async () => {
			const store = useFilesStore()
			store.selectedFileId = 100
			store.files[100] = { id: 100, filesCount: 1 }

			axios.delete.mockResolvedValue({})

			await store.removeFilesFromEnvelope([1, 2, 3, 4, 5])

			expect(store.files[100].filesCount).toBe(0) // Math.max(0, 1 - 5)
		})
	})

	describe('REGRA: cancelamento de upload tem tratamento especial', () => {
		it('ERR_CANCELED retorna mensagem específica', async () => {
			const store = useFilesStore()

			const error = new Error('Cancelled')
			error.code = 'ERR_CANCELED'
			axios.post.mockRejectedValue(error)

			const result = await store.addFilesToEnvelope('uuid', new FormData())

			expect(result.success).toBe(false)
			expect(result.message).toBe('Upload cancelled')
		})
	})
})
