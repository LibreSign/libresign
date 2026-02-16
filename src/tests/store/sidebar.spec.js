/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('vue', async () => {
	const actual = await vi.importActual('vue')
	const Vue = actual.default ?? actual
	return {
		...actual,
		default: Object.assign(Vue, {
			set: vi.fn((obj, key, value) => {
				obj[key] = value
			}),
		}),
	}
})

describe('sidebar store - visibility rules', () => {
	let useSidebarStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		vi.clearAllMocks()

		const module = await import('../../store/sidebar.js')
		useSidebarStore = module.useSidebarStore
	})

	describe('business rule: sidebar can only be shown if it has an active tab', () => {
		it('cannot show sidebar without active tab', () => {
			const store = useSidebarStore()
			store.show = false
			store.activeTab = ''

			expect(store.canShow).toBe(false)
		})

		it('can show sidebar when it has active tab', () => {
			const store = useSidebarStore()
			store.show = false
			store.activeTab = 'sign-tab'

			expect(store.canShow).toBe(true)
		})

		it('cannot show if already visible', () => {
			const store = useSidebarStore()
			store.show = true
			store.activeTab = 'sign-tab'

			expect(store.canShow).toBe(false)
		})
	})

	describe('business rule: sidebar is only visible if show=true AND has active tab', () => {
		it('not visible if show=false even with tab', () => {
			const store = useSidebarStore()
			store.show = false
			store.activeTab = 'sign-tab'

			expect(store.isVisible).toBe(false)
		})

		it('not visible if show=true but without tab', () => {
			const store = useSidebarStore()
			store.show = true
			store.activeTab = ''

			expect(store.isVisible).toBe(false)
		})

		it('visible only when show=true AND has active tab', () => {
			const store = useSidebarStore()
			store.show = true
			store.activeTab = 'sign-tab'

			expect(store.isVisible).toBe(true)
		})
	})

	describe('business rule: setActiveTab should show sidebar when tab is defined', () => {
		it('activating tab should show sidebar automatically', () => {
			const store = useSidebarStore()
			store.show = false

			store.setActiveTab('sign-tab')

			expect(store.activeTab).toBe('sign-tab')
			expect(store.show).toBe(true)
		})

		it('clearing tab (null) should hide sidebar', () => {
			const store = useSidebarStore()
			store.show = true
			store.activeTab = 'sign-tab'

			store.setActiveTab(null)

			expect(store.activeTab).toBe('')
			expect(store.show).toBe(false)
		})

		it('clearing tab (undefined) should hide sidebar', () => {
			const store = useSidebarStore()
			store.show = true
			store.activeTab = 'sign-tab'

			store.setActiveTab(undefined)

			expect(store.activeTab).toBe('')
			expect(store.show).toBe(false)
		})
	})

	describe('business rule: shortcuts for specific tabs', () => {
		it('activeSignTab should activate signature tab and show sidebar', () => {
			const store = useSidebarStore()
			store.show = false

			store.activeSignTab()

			expect(store.activeTab).toBe('sign-tab')
			expect(store.show).toBe(true)
		})

		it('activeRequestSignatureTab should activate request tab and show sidebar', () => {
			const store = useSidebarStore()
			store.show = false

			store.activeRequestSignatureTab()

			expect(store.activeTab).toBe('request-signature-tab')
			expect(store.show).toBe(true)
		})
	})

	describe('business rule: toggle should invert visibility state', () => {
		it('toggle changes from hidden to visible', () => {
			const store = useSidebarStore()
			store.show = false

			store.toggleSidebar()

			expect(store.show).toBe(true)
		})

		it('toggle changes from visible to hidden', () => {
			const store = useSidebarStore()
			store.show = true

			store.toggleSidebar()

			expect(store.show).toBe(false)
		})

		it('toggle multiple times switches correctly', () => {
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
