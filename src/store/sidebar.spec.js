/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('vue', () => ({
	set: vi.fn((obj, key, value) => {
		obj[key] = value
	}),
}))

describe('sidebar store - regras de visibilidade', () => {
	let useSidebarStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		vi.clearAllMocks()

		const module = await import('./sidebar.js')
		useSidebarStore = module.useSidebarStore
	})

	describe('regra de negócio: sidebar só pode ser mostrado se tiver uma aba ativa', () => {
		it('não pode mostrar sidebar sem aba ativa', () => {
			const store = useSidebarStore()
			store.show = false
			store.activeTab = ''

			expect(store.canShow()).toBe(false)
		})

		it('pode mostrar sidebar quando tem aba ativa', () => {
			const store = useSidebarStore()
			store.show = false
			store.activeTab = 'sign-tab'

			expect(store.canShow()).toBe(true)
		})

		it('não pode mostrar se já está visível', () => {
			const store = useSidebarStore()
			store.show = true
			store.activeTab = 'sign-tab'

			expect(store.canShow()).toBe(false)
		})
	})

	describe('regra de negócio: sidebar só está visível se estiver show=true E com aba ativa', () => {
		it('não está visível se show=false mesmo com aba', () => {
			const store = useSidebarStore()
			store.show = false
			store.activeTab = 'sign-tab'

			expect(store.isVisible()).toBe(false)
		})

		it('não está visível se show=true mas sem aba', () => {
			const store = useSidebarStore()
			store.show = true
			store.activeTab = ''

			expect(store.isVisible()).toBe(false)
		})

		it('está visível apenas quando show=true E tem aba ativa', () => {
			const store = useSidebarStore()
			store.show = true
			store.activeTab = 'sign-tab'

			expect(store.isVisible()).toBe(true)
		})
	})

	describe('regra de negócio: setActiveTab deve mostrar sidebar quando aba é definida', () => {
		it('ativar aba deve mostrar sidebar automaticamente', () => {
			const store = useSidebarStore()
			store.show = false

			store.setActiveTab('sign-tab')

			expect(store.activeTab).toBe('sign-tab')
			expect(store.show).toBe(true)
		})

		it('limpar aba (null) deve esconder sidebar', () => {
			const store = useSidebarStore()
			store.show = true
			store.activeTab = 'sign-tab'

			store.setActiveTab(null)

			expect(store.activeTab).toBe('')
			expect(store.show).toBe(false)
		})

		it('limpar aba (undefined) deve esconder sidebar', () => {
			const store = useSidebarStore()
			store.show = true
			store.activeTab = 'sign-tab'

			store.setActiveTab(undefined)

			expect(store.activeTab).toBe('')
			expect(store.show).toBe(false)
		})
	})

	describe('regra de negócio: atalhos para abas específicas', () => {
		it('activeSignTab deve ativar aba de assinatura e mostrar sidebar', () => {
			const store = useSidebarStore()
			store.show = false

			store.activeSignTab()

			expect(store.activeTab).toBe('sign-tab')
			expect(store.show).toBe(true)
		})

		it('activeRequestSignatureTab deve ativar aba de solicitação e mostrar sidebar', () => {
			const store = useSidebarStore()
			store.show = false

			store.activeRequestSignatureTab()

			expect(store.activeTab).toBe('request-signature-tab')
			expect(store.show).toBe(true)
		})
	})

	describe('regra de negócio: toggle deve inverter estado de visibilidade', () => {
		it('toggle muda de escondido para visível', () => {
			const store = useSidebarStore()
			store.show = false

			store.toggleSidebar()

			expect(store.show).toBe(true)
		})

		it('toggle muda de visível para escondido', () => {
			const store = useSidebarStore()
			store.show = true

			store.toggleSidebar()

			expect(store.show).toBe(false)
		})

		it('toggle múltiplas vezes alterna corretamente', () => {
			const store = useSidebarStore()
			store.show = false

			store.toggleSidebar() // true
			expect(store.show).toBe(true)

			store.toggleSidebar() // false
			expect(store.show).toBe(false)

			store.toggleSidebar() // true
			expect(store.show).toBe(true)
		})
	})
})
