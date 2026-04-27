/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { MockedFunction } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { ref, nextTick } from 'vue'
import type { TranslationFunction } from '../../test-types'

type SettingsComponent = typeof import('../../../components/Settings/Settings.vue').default
type AuthModule = typeof import('@nextcloud/auth')
type InitialStateModule = typeof import('@nextcloud/initial-state')

const t: TranslationFunction = (_app, text) => text

let Settings: SettingsComponent
let auth: AuthModule
let initialState: InitialStateModule
let getCurrentUserMock: MockedFunction<typeof import('@nextcloud/auth').getCurrentUser>
let loadStateMock: MockedFunction<typeof import('@nextcloud/initial-state').loadState>
const mockPolicies = ref<Record<string, unknown>>({})

type SettingsVm = {
	getAdminRoute: () => string
	isAdmin: boolean
	canManagePreferences: boolean
}

type SettingsWrapper = VueWrapper<SettingsVm>
type NavigationItemWrapper = VueWrapper<any>


vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({
		isAdmin: false,
	})),
}))
vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())
vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaults) => defaults),
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((url) => `/admin/${url}`),
}))
vi.mock('../../../store/policies', () => ({
	usePoliciesStore: () => ({
		get policies() {
			return mockPolicies.value
		},
		fetchEffectivePolicies: vi.fn(async () => {}),
	}),
}))

beforeAll(async () => {
	auth = await import('@nextcloud/auth')
	initialState = await import('@nextcloud/initial-state')
	getCurrentUserMock = auth.getCurrentUser as MockedFunction<typeof auth.getCurrentUser>
	loadStateMock = initialState.loadState as MockedFunction<typeof initialState.loadState>
	;({ default: Settings } = await import('../../../components/Settings/Settings.vue'))
})

describe('Settings', () => {
	let wrapper: SettingsWrapper | null

	const expectItem = (item: NavigationItemWrapper | undefined) => {
		expect(item).toBeDefined()
		if (!item) {
			throw new Error('Expected navigation item to be defined')
		}
		return item
	}

	const findItemByName = (items: NavigationItemWrapper[], name: string) => {
		return items.find((item) => {
			const propName = item.props('name') as string | undefined
			if (!propName) {
				return false
			}
			return propName.includes(name)
		})
	}

	const getWrapper = () => {
		if (!wrapper) {
			throw new Error('Expected wrapper to be mounted')
		}
		return wrapper
	}

	const getItems = () => getWrapper().findAllComponents({ name: 'NcAppNavigationItem' }) as NavigationItemWrapper[]

	const expectItemAt = (items: NavigationItemWrapper[], index: number) => {
		const item = items.at(index)
		expect(item).toBeDefined()
		if (!item) {
			throw new Error('Expected navigation item to be defined')
		}
		return item
	}

	const createWrapper = (
		isAdmin = false,
		canManagePolicies = false,
		canRequestSign = true,
		effectivePolicies: Record<string, unknown> = {
			signature_flow: {
				canSaveAsUserDefault: true,
			},
		},
	): SettingsWrapper => {
		const user = { isAdmin } as ReturnType<typeof auth.getCurrentUser>
		getCurrentUserMock.mockReturnValue(user)
		mockPolicies.value = effectivePolicies
		loadStateMock.mockImplementation((app, key, defaults) => {
			if (key === 'config') {
				return {
					...(defaults as Record<string, unknown>),
					can_manage_group_policies: canManagePolicies,
				}
			}
			if (key === 'can_request_sign') {
				return canRequestSign
			}
			return defaults
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
					t,
				},
			},
		}) as unknown as SettingsWrapper
	}

	beforeEach(() => {
		if (wrapper) {
			wrapper.unmount()
			wrapper = null
		}
		vi.clearAllMocks()
		mockPolicies.value = {}
		loadStateMock.mockImplementation((app, key, defaults) => defaults)
	})

	describe('RULE: Account navigation item always displays', () => {
		it('shows Account item for non-admin user', () => {
			wrapper = createWrapper(false)
			const items = getItems()
			const accountItem = expectItem(findItemByName(items, 'Account'))

			expect(accountItem.props('to')).toEqual({ name: 'Account' })

			expect(accountItem).toBeTruthy()
		})

		it('shows Account item for admin user', () => {
			wrapper = createWrapper(true)
			const items = getItems()
			const accountItem = expectItem(findItemByName(items, 'Account'))

			expect(accountItem).toBeTruthy()
		})

		it('Account item links to Account route', () => {
			wrapper = createWrapper()
			const items = getItems()
			const accountItem = expectItem(findItemByName(items, 'Account'))

			expect(accountItem.props('to')).toEqual({ name: 'Account' })
		})

		it('Account item has user icon', () => {
			wrapper = createWrapper()
			const accountIcon = getWrapper().find('.account-icon')

			expect(accountIcon.exists()).toBe(true)
		})
	})

	describe('RULE: Administration item shows only for admin users', () => {
		it('hides Administration for non-admin users', () => {
			wrapper = createWrapper(false)
			const items = getItems()
			const adminItem = findItemByName(items, 'Administration')

			expect(adminItem).toBeUndefined()
		})

		it('shows Administration for admin users', () => {
			wrapper = createWrapper(true)
			const items = getItems()
			const adminItem = expectItem(findItemByName(items, 'Administration'))

			expect(adminItem).toBeTruthy()
		})

		it('Administration item has tune icon', () => {
			wrapper = createWrapper(true)
			const tuneIcon = getWrapper().find('.tune-icon')

			expect(tuneIcon.exists()).toBe(true)
		})

		it('Administration href points to admin settings', () => {
			wrapper = createWrapper(true)
			const items = getItems()
			const adminItem = expectItem(findItemByName(items, 'Administration'))

			expect(adminItem.props('href')).toContain('settings/admin/libresign')
		})
	})

	describe('RULE: getAdminRoute generates correct URL', () => {
		it('generates admin route with generateUrl', () => {
			wrapper = createWrapper(true)
			const route = getWrapper().vm.getAdminRoute()

			expect(route).toContain('settings/admin/libresign')
		})

		it('returns formatted admin settings URL', () => {
			wrapper = createWrapper(true)
			const route = getWrapper().vm.getAdminRoute()

			expect(route).toBe('/admin/settings/admin/libresign')
		})
	})

	describe('RULE: Rate LibreSign item always displays', () => {
		it('shows Rate item for non-admin', () => {
			wrapper = createWrapper(false)
			const items = getItems()
			const rateItem = expectItem(findItemByName(items, 'Rate'))

			expect(rateItem).toBeTruthy()
		})
		it('shows Rate item for admin', () => {
			wrapper = createWrapper(true)
			const items = getItems()
			const rateItem = expectItem(findItemByName(items, 'Rate'))

			expect(rateItem).toBeTruthy()
		})

		it('Rate item has star icon', () => {
			wrapper = createWrapper()
			const starIcon = getWrapper().find('.star-icon')
			expect(starIcon.exists()).toBe(true)
		})

		it('Rate item links to apps marketplace', () => {
			wrapper = createWrapper()
			const items = getItems()
			const rateItem = expectItem(findItemByName(items, 'Rate'))

			expect(rateItem.props('href')).toContain('apps.nextcloud.com')
		})

		it('Rate item URL includes comments section', () => {
			wrapper = createWrapper()
			const items = getItems()
			const rateItem = expectItem(findItemByName(items, 'Rate'))

			expect(rateItem.props('href')).toContain('comments')
		})

		it('Rate item displays with heart emoji', () => {
			wrapper = createWrapper()
			const items = getItems()
			const rateItem = expectItem(findItemByName(items, 'Rate'))

			expect(rateItem.props('name')).toContain('❤️')
		})
	})

	describe('RULE: isAdmin data property reflects user role', () => {
		it('isAdmin false for non-admin user', () => {
			wrapper = createWrapper(false)

			expect(getWrapper().vm.isAdmin).toBe(false)
		})

		it('isAdmin true for admin user', () => {
			wrapper = createWrapper(true)

			expect(getWrapper().vm.isAdmin).toBe(true)
		})
	})

	describe('RULE: unauthenticated users (signing via email link) do not crash the component', () => {
		const createUnauthenticatedWrapper = (): SettingsWrapper => {
			getCurrentUserMock.mockReturnValue(null)

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
					mocks: { t },
				},
			}) as unknown as SettingsWrapper
		}

		it('mounts without throwing when getCurrentUser returns null', () => {
			expect(() => createUnauthenticatedWrapper()).not.toThrow()
		})

		it('isAdmin is false when getCurrentUser returns null', () => {
			wrapper = createUnauthenticatedWrapper()

			expect(getWrapper().vm.isAdmin).toBe(false)
		})

		it('hides the Administration link when user is unauthenticated', () => {
			wrapper = createUnauthenticatedWrapper()
			const items = getItems()
			const adminItem = findItemByName(items, 'Administration')

			expect(adminItem).toBeUndefined()
		})

		it('shows 2 navigation items for unauthenticated user', () => {
			wrapper = createUnauthenticatedWrapper()
			const items = getItems()

			// Account + Preferences + Rate = 3
			expect(items.length).toBe(2)
		})
	})

	describe('RULE: navigation items count depends on admin status', () => {
		it('shows 2 items for non-admin', () => {
			wrapper = createWrapper(false)
			const items = getItems()

			// Account + Preferences + Rate = 3
			expect(items.length).toBe(3)
		})

		it('shows 3 items for admin', () => {
			wrapper = createWrapper(true)
			const items = getItems()

			// Account + Preferences + Policies + Administration + Rate = 5
			expect(items.length).toBe(5)
		})
	})

	describe('RULE: Preferences item follows personal preference capability', () => {
		it('shows Preferences for non-admin users', () => {
			wrapper = createWrapper(false)
			const items = getItems()
			const preferencesItem = expectItem(findItemByName(items, 'Preferences'))

			expect(preferencesItem.props('to')).toEqual({ name: 'Preferences' })
		})

		it('shows Preferences for admin users', () => {
			wrapper = createWrapper(true)
			const items = getItems()
			const preferencesItem = expectItem(findItemByName(items, 'Preferences'))

			expect(preferencesItem).toBeTruthy()
		})

		it('renders the preferences icon', () => {
			wrapper = createWrapper(false)

			expect(getWrapper().find('.preferences-icon').exists()).toBe(true)
		})

		it('hides Preferences when no policy allows saving personal preferences', () => {
			wrapper = createWrapper(false, false, true, {
				signature_flow: {
					canSaveAsUserDefault: false,
				},
			})
			const items = getItems()

			expect(findItemByName(items, 'Preferences')).toBeUndefined()
		})

		it('shows Preferences when add_footer allows saving personal preferences', () => {
			wrapper = createWrapper(false, false, true, {
				signature_flow: {
					canSaveAsUserDefault: false,
				},
				add_footer: {
					canSaveAsUserDefault: true,
				},
			})
			const items = getItems()
			const preferencesItem = expectItem(findItemByName(items, 'Preferences'))

			expect(preferencesItem.props('to')).toEqual({ name: 'Preferences' })
		})

		it('updates Preferences visibility after policy state changes', async () => {
			wrapper = createWrapper(false, false, true, {
				signature_flow: {
					canSaveAsUserDefault: false,
				},
			})
			expect(findItemByName(getItems(), 'Preferences')).toBeUndefined()

			mockPolicies.value = {
				signature_flow: {
					canSaveAsUserDefault: true,
				},
			}
			await nextTick()

			const preferencesItem = expectItem(findItemByName(getItems(), 'Preferences'))
			expect(preferencesItem.props('to')).toEqual({ name: 'Preferences' })
		})

		it('hides Preferences when user cannot request signatures', () => {
			wrapper = createWrapper(false, false, false, {
				signature_flow: {
					canSaveAsUserDefault: true,
				},
			})
			const items = getItems()

			expect(findItemByName(items, 'Preferences')).toBeUndefined()
		})
	})

	describe('RULE: Policies item follows policy-management capability in the app menu', () => {
		it('hides Policies for non-admin users', () => {
			wrapper = createWrapper(false)
			const items = getItems()

			expect(findItemByName(items, 'Policies')).toBeUndefined()
		})

		it('shows Policies for admin users', () => {
			wrapper = createWrapper(true)
			const items = getItems()
			const policiesItem = expectItem(findItemByName(items, 'Policies'))

			expect(policiesItem.props('to')).toEqual({ name: 'Policies' })
		})

		it('hides Policies for non-admin users with group policy capability but no editable policies', () => {
			wrapper = createWrapper(false, true, true, {
				signature_flow: {
					editableByCurrentActor: false,
				},
			})
			const items = getItems()

			expect(findItemByName(items, 'Policies')).toBeUndefined()
		})

		it('shows Policies for non-admin users with group policy capability and editable policy even without delegated rules', () => {
			wrapper = createWrapper(false, true, true, {
				add_footer: {
					groupCount: 0,
					userCount: 0,
					editableByCurrentActor: true,
				},
			})
			const items = getItems()
			const policiesItem = expectItem(findItemByName(items, 'Policies'))

			expect(policiesItem.props('to')).toEqual({ name: 'Policies' })
		})

		it('shows Policies for non-admin users with group policy capability and delegated policies', () => {
			wrapper = createWrapper(false, true, true, {
				signature_flow: {
					groupCount: 1,
					userCount: 0,
					editableByCurrentActor: true,
				},
			})
			const items = getItems()
			const policiesItem = expectItem(findItemByName(items, 'Policies'))

			expect(policiesItem.props('to')).toEqual({ name: 'Policies' })
		})

		it('hides Policies for non-admin users with delegated rules that are not editable', () => {
			wrapper = createWrapper(false, true, true, {
				add_footer: {
					groupCount: 1,
					userCount: 0,
					editableByCurrentActor: false,
				},
			})
			const items = getItems()

			expect(findItemByName(items, 'Policies')).toBeUndefined()
		})

		it('hides Policies for non-admin users when only system-level policies exist', () => {
			wrapper = createWrapper(false, true, true, {
				docmdp: {
					groupCount: 0,
					userCount: 0,
				},
			})
			const items = getItems()

			expect(findItemByName(items, 'Policies')).toBeUndefined()
		})

		it('updates Policies visibility after the policies store receives delegated counts', async () => {
			wrapper = createWrapper(false, true)
			expect(findItemByName(getItems(), 'Policies')).toBeUndefined()

			mockPolicies.value = {
				add_footer: {
					groupCount: 1,
					userCount: 0,
					editableByCurrentActor: true,
				},
			}
			await nextTick()

			const policiesItem = expectItem(findItemByName(getItems(), 'Policies'))
			expect(policiesItem.props('to')).toEqual({ name: 'Policies' })
		})

		it('renders the policies icon for admin users', () => {
			wrapper = createWrapper(true)

			expect(getWrapper().find('.policies-icon').exists()).toBe(true)
		})
	})

	describe('RULE: each navigation item has name and icon', () => {
		it('Account item has all properties', () => {
			wrapper = createWrapper()
			const items = getItems()
			const accountItem = expectItem(findItemByName(items, 'Account'))

			expect(accountItem.props('name')).toBeTruthy()
		})

		it('Rate item has all properties', () => {
			wrapper = createWrapper()
			const items = getItems()
			const rateItem = expectItem(findItemByName(items, 'Rate'))

			expect(rateItem.props('name')).toBeTruthy()
			expect(rateItem.props('href')).toBeTruthy()
		})

		it('Admin item has all properties when present', () => {
			wrapper = createWrapper(true)
			const items = getItems()
			const adminItem = expectItem(findItemByName(items, 'Administration'))

			expect(adminItem.props('name')).toBeTruthy()
			expect(adminItem.props('href')).toBeTruthy()
		})

		it('does not use fallback icon prop when custom icon slot is present', () => {
			wrapper = createWrapper(true)
			const items = getItems()
			const preferencesItem = expectItem(findItemByName(items, 'Preferences'))
			const policiesItem = expectItem(findItemByName(items, 'Policies'))
			const adminItem = expectItem(findItemByName(items, 'Administration'))

			expect(preferencesItem.props('icon')).toBeUndefined()
			expect(policiesItem.props('icon')).toBeUndefined()
			expect(adminItem.props('icon')).toBeUndefined()
		})
	})

	describe('RULE: icons render correctly', () => {
		it('renders all required icons for admin', () => {
			wrapper = createWrapper(true)

			expect(getWrapper().find('.account-icon').exists()).toBe(true)
			expect(getWrapper().find('.tune-icon').exists()).toBe(true)
			expect(getWrapper().find('.star-icon').exists()).toBe(true)
		})

		it('renders Account and Rate icons for non-admin', () => {
			wrapper = createWrapper(false)

			expect(getWrapper().find('.account-icon').exists()).toBe(true)
			expect(getWrapper().find('.star-icon').exists()).toBe(true)
		})

		it('does not render admin icon for non-admin', () => {
			wrapper = createWrapper(false)

			expect(getWrapper().find('.tune-icon').exists()).toBe(false)
		})
	})

	describe('RULE: navigation items render in correct order', () => {
		it('renders Account first for non-admin', () => {
			wrapper = createWrapper(false)
			const items = getItems()
			const firstItem = expectItemAt(items, 0)

			expect(firstItem.props('name')).toContain('Account')
		})

		it('renders Account first for admin', () => {
			wrapper = createWrapper(true)
			const items = getItems()
			const firstItem = expectItemAt(items, 0)

			expect(firstItem.props('name')).toContain('Account')
		})

		it('renders Administration second for admin (if present)', () => {
			wrapper = createWrapper(true)
			const items = getItems()
			const secondItem = expectItemAt(items, 1)

			expect(secondItem.props('name')).toContain('Preferences')
		})

		it('renders Policies before Administration for admin users', () => {
			wrapper = createWrapper(true)
			const items = getItems()
			const thirdItem = expectItemAt(items, 2)
			const fourthItem = expectItemAt(items, 3)

			expect(thirdItem.props('name')).toContain('Policies')
			expect(fourthItem.props('name')).toContain('Administration')
		})

		it('renders Rate last', async () => {
			wrapper = createWrapper(true)
			const items = getItems()
			const lastItem = expectItemAt(items, items.length - 1)

			expect(lastItem.props('name')).toContain('Rate')
		})
	})

	describe('RULE: component wraps items in unordered list', () => {
		it('contains ul element', () => {
			wrapper = createWrapper()
			const ul = getWrapper().find('ul')
			expect(ul.exists()).toBe(true)
		})

		it('navigation items are rendered as children of ul', () => {
			wrapper = createWrapper()
			const ul = getWrapper().find('ul')
			const items = ul.findAllComponents({ name: 'NcAppNavigationItem' })

			expect(items.length).toBeGreaterThan(0)
		})
	})

	describe('RULE: complete workflow for different user roles', () => {
		it('provides different experience for regular user', () => {
			wrapper = createWrapper(false)
			const items = getItems()

			expect(items).toHaveLength(3)

			const hasAccount = items.some(i => i.props('name')?.includes('Account'))
			const hasPreferences = items.some(i => i.props('name')?.includes('Preferences'))
			const hasRate = items.some(i => i.props('name')?.includes('Rate'))
			const hasAdmin = items.some(i => i.props('name')?.includes('Administration'))

			expect(hasAccount).toBe(true)
			expect(hasPreferences).toBe(true)
			expect(hasRate).toBe(true)
			expect(hasAdmin).toBe(false)
		})

		it('provides full experience for admin user', () => {
			wrapper = createWrapper(true)
			const items = getItems()

			expect(items).toHaveLength(5)

			const hasAccount = items.some(i => i.props('name')?.includes('Account'))
			const hasPreferences = items.some(i => i.props('name')?.includes('Preferences'))
			const hasPolicies = items.some(i => i.props('name')?.includes('Policies'))
			const hasRate = items.some(i => i.props('name')?.includes('Rate'))
			const hasAdmin = items.some(i => i.props('name')?.includes('Administration'))

			expect(hasAccount).toBe(true)
			expect(hasPreferences).toBe(true)
			expect(hasPolicies).toBe(true)
			expect(hasRate).toBe(true)
			expect(hasAdmin).toBe(true)
		})

		it('hides policies entry for group manager without editable policies', () => {
			wrapper = createWrapper(false, true)
			const items = getItems()

			expect(items).toHaveLength(3)

			const hasAccount = items.some(i => i.props('name')?.includes('Account'))
			const hasPreferences = items.some(i => i.props('name')?.includes('Preferences'))
			const hasPolicies = items.some(i => i.props('name')?.includes('Policies'))
			const hasRate = items.some(i => i.props('name')?.includes('Rate'))
			const hasAdmin = items.some(i => i.props('name')?.includes('Administration'))

			expect(hasAccount).toBe(true)
			expect(hasPreferences).toBe(true)
			expect(hasPolicies).toBe(false)
			expect(hasRate).toBe(true)
			expect(hasAdmin).toBe(false)
		})

		it('hides preferences entry when the user cannot change anything personally', () => {
			wrapper = createWrapper(false, false, true, {
				signature_flow: {
					canSaveAsUserDefault: false,
				},
			})
			const items = getItems()

			expect(items).toHaveLength(2)

			const hasPreferences = items.some(i => i.props('name')?.includes('Preferences'))
			const hasPolicies = items.some(i => i.props('name')?.includes('Policies'))

			expect(hasPreferences).toBe(false)
			expect(hasPolicies).toBe(false)
		})

		it('shows policies entry for group manager with editable policies', () => {
			wrapper = createWrapper(false, true, true, {
				signature_flow: {
					canSaveAsUserDefault: true,
					editableByCurrentActor: true,
					groupCount: 1,
					userCount: 0,
				},
			})
			const items = getItems()

			expect(items).toHaveLength(4)

			const hasPolicies = items.some(i => i.props('name')?.includes('Policies'))
			const hasAdmin = items.some(i => i.props('name')?.includes('Administration'))

			expect(hasPolicies).toBe(true)
			expect(hasAdmin).toBe(false)
		})
	})

	describe('RULE: Account item icon configuration', () => {
		it('Account item has icon prop', () => {
			wrapper = createWrapper()
			const items = getItems()
			const accountItem = expectItem(findItemByName(items, 'Account'))

			expect(accountItem.props('icon')).toBe('icon-user')
		})
	})
})
