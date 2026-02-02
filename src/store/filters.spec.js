/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

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
	generateOcsUrl: vi.fn((path, params) => {
		let url = `/ocs/v2.php${path}`
		if (params) {
			Object.entries(params).forEach(([key, value]) => {
				url = url.replace(`{${key}}`, value)
			})
		}
		return url
	}),
}))

vi.mock('../helpers/logger.js', () => ({
	default: {
		debug: vi.fn(),
	},
}))

describe('filters store - regras de negócio de filtros', () => {
	let useFiltersStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		vi.clearAllMocks()

		const module = await import('./filters.js')
		useFiltersStore = module.useFiltersStore
	})

	describe('regra de negócio: activeChips deve retornar todos os chips ativos de todos os filtros', () => {
		it('retorna array vazio quando não há chips', () => {
			const store = useFiltersStore()

			expect(store.activeChips).toEqual([])
		})

		it('retorna chips de um único filtro', () => {
			const store = useFiltersStore()
			const signedChip = { id: 'signed', label: 'Signed' }
			store.chips = {
				status: [signedChip],
			}

			expect(store.activeChips).toEqual([signedChip])
		})

		it('retorna chips de múltiplos filtros em um único array', () => {
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

		it('retorna múltiplos chips do mesmo filtro', () => {
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

	describe('regra de negócio: filterStatusArray deve converter JSON string em array', () => {
		it('retorna array vazio quando filter_status é string vazia', () => {
			const store = useFiltersStore()
			store.filter_status = ''

			expect(store.filterStatusArray).toEqual([])
		})

		it('retorna array vazio quando filter_status é JSON inválido', () => {
			const originalError = console.error
			console.error = vi.fn()

			const store = useFiltersStore()
			store.filter_status = 'invalid json'

			expect(store.filterStatusArray).toEqual([])
			console.error = originalError
		})

		it('converte JSON válido em array', () => {
			const store = useFiltersStore()
			store.filter_status = '["signed","pending"]'

			expect(store.filterStatusArray).toEqual(['signed', 'pending'])
		})

		it('converte array de objetos JSON', () => {
			const store = useFiltersStore()
			store.filter_status = '[{"id":1},{"id":2}]'

			expect(store.filterStatusArray).toEqual([{ id: 1 }, { id: 2 }])
		})
	})

	describe('regra de negócio: atualização de chips deve emitir evento de filtro', () => {
		it('onFilterUpdateChips deve emitir evento libresign:filters:update', async () => {
			const store = useFiltersStore()
			const event = {
				id: 'status',
				detail: [{ id: 'signed', label: 'Signed' }],
			}

			await store.onFilterUpdateChips(event)

			expect(emit).toHaveBeenCalledWith('libresign:filters:update')
		})

		it('onFilterUpdateChips deve atualizar chips do filtro específico', async () => {
			const store = useFiltersStore()
			const event = {
				id: 'status',
				detail: [{ id: 'signed', label: 'Signed' }],
			}

			await store.onFilterUpdateChips(event)

			expect(store.chips.status).toEqual([{ id: 'signed', label: 'Signed' }])
		})

		it('onFilterUpdateChips não deve sobrescrever outros filtros', async () => {
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

	describe('regra de negócio: filtro de modificação deve salvar no servidor', () => {
		it('filtro modified deve salvar primeiro chip ID no servidor', async () => {
			axios.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			const event = {
				id: 'modified',
				detail: [{ id: 'today', label: 'Today' }],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(axios.put).toHaveBeenCalledWith(
				'/ocs/v2.php/apps/libresign/api/v1/account/config/filter_modified',
				{ value: 'today' }
			)
		})

		it('filtro modified com múltiplos chips deve salvar apenas o primeiro', async () => {
			axios.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			const event = {
				id: 'modified',
				detail: [
					{ id: 'today', label: 'Today' },
					{ id: 'yesterday', label: 'Yesterday' },
				],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(axios.put).toHaveBeenCalledWith(
				'/ocs/v2.php/apps/libresign/api/v1/account/config/filter_modified',
				{ value: 'today' }
			)
		})

		it('filtro modified vazio deve salvar string vazia', async () => {
			axios.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			const event = {
				id: 'modified',
				detail: [],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(axios.put).toHaveBeenCalledWith(
				'/ocs/v2.php/apps/libresign/api/v1/account/config/filter_modified',
				{ value: '' }
			)
		})
	})

	describe('regra de negócio: filtro de status deve salvar array JSON no servidor', () => {
		it('filtro status deve salvar array de IDs como JSON', async () => {
			axios.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			const event = {
				id: 'status',
				detail: [
					{ id: 'signed', label: 'Signed' },
					{ id: 'pending', label: 'Pending' },
				],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(axios.put).toHaveBeenCalledWith(
				'/ocs/v2.php/apps/libresign/api/v1/account/config/filter_status',
				{ value: '["signed","pending"]' }
			)
		})

		it('filtro status vazio deve salvar string vazia', async () => {
			axios.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			const event = {
				id: 'status',
				detail: [],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(axios.put).toHaveBeenCalledWith(
				'/ocs/v2.php/apps/libresign/api/v1/account/config/filter_status',
				{ value: '' }
			)
		})

		it('filtro status deve atualizar estado local após salvar', async () => {
			axios.put.mockResolvedValue({ data: { success: true } })

			const store = useFiltersStore()
			const event = {
				id: 'status',
				detail: [{ id: 'signed', label: 'Signed' }],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(store.filter_status).toBe('["signed"]')
		})
	})

	describe('regra de negócio: apenas filtros modified e status devem salvar no servidor', () => {
		it('filtro com ID diferente não deve chamar API', async () => {
			const store = useFiltersStore()
			const event = {
				id: 'other_filter',
				detail: [{ id: 'value', label: 'Value' }],
			}

			await store.onFilterUpdateChipsAndSave(event)

			expect(axios.put).not.toHaveBeenCalled()
		})

		it('filtro com ID diferente ainda deve emitir evento', async () => {
			const store = useFiltersStore()
			const event = {
				id: 'other_filter',
				detail: [{ id: 'value', label: 'Value' }],
			}

			await store.onFilterUpdateChipsAndSave(event)

			// Não emite porque não é modified nem status
			expect(emit).not.toHaveBeenCalled()
		})
	})
})
