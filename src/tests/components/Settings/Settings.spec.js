/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
let Settings
let auth


vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({
		isAdmin: false,
	})),
}))
vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((app, text) => text),
	translatePlural: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
	t: vi.fn((app, text) => text),
	n: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((url) => `/admin/${url}`),
}))

beforeAll(async () => {
	auth = await import('@nextcloud/auth')
	;({ default: Settings } = await import('../../../components/Settings/Settings.vue'))
})

describe('Settings', () => {
	let wrapper

	const createWrapper = (isAdmin = false) => {
		auth.getCurrentUser.mockReturnValue({
			isAdmin,
		})

		return mount(Settings, {
			global: {
				stubs: {
					NcAppNavigationItem: {
						name: 'NcAppNavigationItem',
						props: ['name', 'to', 'href', 'icon'],
						template: '<li><slot name="icon" /><span class="item-name">{{ name }}</span><slot /></li>',
					},
					AccountIcon: { template: '<div class="account-icon"></div>' },
					StarIcon: { template: '<div class="star-icon"></div>' },
					TuneIcon: { template: '<div class="tune-icon"></div>' },
				},
				mocks: {
					t: (app, text) => text,
				},
			},
		})
	}

	const findItemByName = (items, name) => {
		return items.find(item => (item.props('name') || '').includes(name))
	}

	beforeEach(() => {
		if (wrapper) {
			wrapper.unmount()
		}
		vi.clearAllMocks()
	})

	describe('RULE: Account navigation item always displays', () => {
		it('shows Account item for non-admin user', () => {
			wrapper = createWrapper(false)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const accountItem = findItemByName(items, 'Account')

			expect(accountItem).toBeTruthy()
		})

		it('shows Account item for admin user', () => {
			wrapper = createWrapper(true)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const accountItem = findItemByName(items, 'Account')

			expect(accountItem).toBeTruthy()
		})

		it('Account item links to Account route', () => {
			wrapper = createWrapper()

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const accountItem = findItemByName(items, 'Account')

			expect(accountItem.props('to')).toEqual({ name: 'Account' })
		})

		it('Account item has user icon', () => {
			wrapper = createWrapper()

			const accountIcon = wrapper.find('.account-icon')
			expect(accountIcon.exists()).toBe(true)
		})
	})

	describe('RULE: Administration item shows only for admin users', () => {
		it('hides Administration for non-admin users', () => {
			wrapper = createWrapper(false)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const adminItem = findItemByName(items, 'Administration')

			expect(adminItem).toBeUndefined()
		})

		it('shows Administration for admin users', () => {
			wrapper = createWrapper(true)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const adminItem = findItemByName(items, 'Administration')

			expect(adminItem).toBeTruthy()
		})

		it('Administration item has tune icon', () => {
			wrapper = createWrapper(true)

			const tuneIcon = wrapper.find('.tune-icon')
			expect(tuneIcon.exists()).toBe(true)
		})

		it('Administration href points to admin settings', () => {
			wrapper = createWrapper(true)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const adminItem = findItemByName(items, 'Administration')

			expect(adminItem.props('href')).toContain('settings/admin/libresign')
		})
	})

	describe('RULE: getAdminRoute generates correct URL', () => {
		it('generates admin route with generateUrl', () => {
			wrapper = createWrapper(true)

			const route = wrapper.vm.getAdminRoute()

			expect(route).toContain('settings/admin/libresign')
		})

		it('returns formatted admin settings URL', () => {
			wrapper = createWrapper(true)

			const route = wrapper.vm.getAdminRoute()

			expect(route).toBe('/admin/settings/admin/libresign')
		})
	})

	describe('RULE: Rate LibreSign item always displays', () => {
		it('shows Rate item for non-admin', () => {
			wrapper = createWrapper(false)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const rateItem = findItemByName(items, 'Rate')

			expect(rateItem).toBeTruthy()
		})

		it('shows Rate item for admin', () => {
			wrapper = createWrapper(true)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const rateItem = findItemByName(items, 'Rate')

			expect(rateItem).toBeTruthy()
		})

		it('Rate item has star icon', () => {
			wrapper = createWrapper()

			const starIcon = wrapper.find('.star-icon')
			expect(starIcon.exists()).toBe(true)
		})

		it('Rate item links to apps marketplace', () => {
			wrapper = createWrapper()

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const rateItem = findItemByName(items, 'Rate')

			expect(rateItem.props('href')).toContain('apps.nextcloud.com')
		})

		it('Rate item URL includes comments section', () => {
			wrapper = createWrapper()

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const rateItem = findItemByName(items, 'Rate')

			expect(rateItem.props('href')).toContain('comments')
		})

		it('Rate item displays with heart emoji', () => {
			wrapper = createWrapper()

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const rateItem = findItemByName(items, 'Rate')

			expect(rateItem.props('name')).toContain('❤️')
		})
	})

	describe('RULE: isAdmin data property reflects user role', () => {
		it('isAdmin false for non-admin user', () => {
			wrapper = createWrapper(false)

			expect(wrapper.vm.isAdmin).toBe(false)
		})

		it('isAdmin true for admin user', () => {
			wrapper = createWrapper(true)

			expect(wrapper.vm.isAdmin).toBe(true)
		})
	})

	describe('RULE: navigation items count depends on admin status', () => {
		it('shows 2 items for non-admin', () => {
			wrapper = createWrapper(false)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })

			// Account + Rate = 2
			expect(items.length).toBe(2)
		})

		it('shows 3 items for admin', () => {
			wrapper = createWrapper(true)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })

			// Account + Administration + Rate = 3
			expect(items.length).toBe(3)
		})
	})

	describe('RULE: each navigation item has name and icon', () => {
		it('Account item has all properties', () => {
			wrapper = createWrapper()

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const accountItem = findItemByName(items, 'Account')

			expect(accountItem.props('name')).toBeTruthy()
		})

		it('Rate item has all properties', () => {
			wrapper = createWrapper()

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const rateItem = findItemByName(items, 'Rate')

			expect(rateItem.props('name')).toBeTruthy()
			expect(rateItem.props('href')).toBeTruthy()
		})

		it('Admin item has all properties when present', () => {
			wrapper = createWrapper(true)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const adminItem = findItemByName(items, 'Administration')

			expect(adminItem.props('name')).toBeTruthy()
			expect(adminItem.props('href')).toBeTruthy()
		})
	})

	describe('RULE: icons render correctly', () => {
		it('renders all required icons for admin', () => {
			wrapper = createWrapper(true)

			expect(wrapper.find('.account-icon').exists()).toBe(true)
			expect(wrapper.find('.tune-icon').exists()).toBe(true)
			expect(wrapper.find('.star-icon').exists()).toBe(true)
		})

		it('renders Account and Rate icons for non-admin', () => {
			wrapper = createWrapper(false)

			expect(wrapper.find('.account-icon').exists()).toBe(true)
			expect(wrapper.find('.star-icon').exists()).toBe(true)
		})

		it('does not render admin icon for non-admin', () => {
			wrapper = createWrapper(false)

			expect(wrapper.find('.tune-icon').exists()).toBe(false)
		})
	})

	describe('RULE: navigation items render in correct order', () => {
		it('renders Account first for non-admin', () => {
			wrapper = createWrapper(false)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })

			expect(items.at(0).props('name')).toContain('Account')
		})

		it('renders Account first for admin', () => {
			wrapper = createWrapper(true)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })

			expect(items.at(0).props('name')).toContain('Account')
		})

		it('renders Administration second for admin (if present)', () => {
			wrapper = createWrapper(true)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })

			expect(items.at(1).props('name')).toContain('Administration')
		})

		it('renders Rate last', async () => {
			wrapper = createWrapper(true)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })

			expect(items.at(items.length - 1).props('name')).toContain('Rate')
		})
	})

	describe('RULE: component wraps items in unordered list', () => {
		it('contains ul element', () => {
			wrapper = createWrapper()

			const ul = wrapper.find('ul')
			expect(ul.exists()).toBe(true)
		})

		it('navigation items are rendered as children of ul', () => {
			wrapper = createWrapper()

			const ul = wrapper.find('ul')
			const items = ul.findAllComponents({ name: 'NcAppNavigationItem' })

			expect(items.length).toBeGreaterThan(0)
		})
	})

	describe('RULE: complete workflow for different user roles', () => {
		it('provides different experience for regular user', () => {
			wrapper = createWrapper(false)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })

			expect(items).toHaveLength(2)

			const hasAccount = items.some(i => i.props('name')?.includes('Account'))
			const hasRate = items.some(i => i.props('name')?.includes('Rate'))
			const hasAdmin = items.some(i => i.props('name')?.includes('Administration'))

			expect(hasAccount).toBe(true)
			expect(hasRate).toBe(true)
			expect(hasAdmin).toBe(false)
		})

		it('provides full experience for admin user', () => {
			wrapper = createWrapper(true)

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })

			expect(items).toHaveLength(3)

			const hasAccount = items.some(i => i.props('name')?.includes('Account'))
			const hasRate = items.some(i => i.props('name')?.includes('Rate'))
			const hasAdmin = items.some(i => i.props('name')?.includes('Administration'))

			expect(hasAccount).toBe(true)
			expect(hasRate).toBe(true)
			expect(hasAdmin).toBe(true)
		})
	})

	describe('RULE: Account item icon configuration', () => {
		it('Account item has icon prop', () => {
			wrapper = createWrapper()

			const items = wrapper.findAllComponents({ name: 'NcAppNavigationItem' })
			const accountItem = findItemByName(items, 'Account')

			expect(accountItem.props('icon')).toBe('icon-user')
		})
	})
})
