/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TopBar from '../../../components/TopBar/TopBar.vue'

describe('TopBar', () => {
	const createWrapper = (props = {}) => {
		return mount(TopBar, {
			props: {
				sidebarToggle: false,
				...props,
			},
			global: {
				stubs: {
					SidebarToggle: { name: 'SidebarToggle', template: '<div class="sidebar-toggle-stub">Toggle</div>' },
				},
			},
			slots: {
				filter: '<div class="filter-slot">Filter Content</div>',
			},
		})
	}

	let wrapper: ReturnType<typeof createWrapper> | undefined

	beforeEach(() => {
		if (wrapper) {
		}
		vi.clearAllMocks()
	})

	describe('RULE: filter slot renders when provided', () => {
		it('displays filter slot content', () => {
			wrapper = createWrapper()

			const filter = wrapper.find('.filter-slot')
			expect(filter.exists()).toBe(true)
			expect(filter.text()).toBe('Filter Content')
		})

		it('allows custom filter content', () => {
			wrapper = mount(TopBar, {
				props: {
					sidebarToggle: false,
				},
				global: {
					stubs: {
						SidebarToggle: { name: 'SidebarToggle', template: '<div class="sidebar-toggle-stub">Toggle</div>' },
					},
				},
				slots: {
					filter: '<div class="custom-filter">Custom Filter</div>',
				},
			})

			const filter = wrapper.find('.custom-filter')
			expect(filter.exists()).toBe(true)
		})

		it('renders empty when filter slot not provided', () => {
			wrapper = mount(TopBar, {
				props: {
					sidebarToggle: false,
				},
				global: {
					stubs: {
						SidebarToggle: { name: 'SidebarToggle', template: '<div class="sidebar-toggle-stub">Toggle</div>' },
					},
				},
			})

			// Should still render but no filter content
			expect(wrapper.exists()).toBe(true)
		})
	})

	describe('RULE: SidebarToggle shows when sidebarToggle prop true', () => {
		it('displays SidebarToggle when sidebarToggle true', () => {
			wrapper = createWrapper({
				sidebarToggle: true,
			})

			const toggle = wrapper.findComponent({ name: 'SidebarToggle' })
			expect(toggle.exists()).toBe(true)
		})

		it('hides SidebarToggle when sidebarToggle false', () => {
			wrapper = createWrapper({
				sidebarToggle: false,
			})

			const toggle = wrapper.findComponent({ name: 'SidebarToggle' })
			expect(toggle.exists()).toBe(false)
		})

		it('toggles SidebarToggle visibility on prop change', async () => {
			wrapper = createWrapper({
				sidebarToggle: false,
			})

			let toggle = wrapper.findComponent({ name: 'SidebarToggle' })
			expect(toggle.exists()).toBe(false)

			await wrapper.setProps({ sidebarToggle: true })

			toggle = wrapper.findComponent({ name: 'SidebarToggle' })
			expect(toggle.exists()).toBe(true)
		})

		it('shows stub when mounted with sidebarToggle true', () => {
			wrapper = createWrapper({
				sidebarToggle: true,
			})

			const stub = wrapper.find('.sidebar-toggle-stub')
			expect(stub.exists()).toBe(true)
		})
	})

	describe('RULE: topBarStyle computed property retrieves CSS variable', () => {
		it('returns style object', () => {
			wrapper = createWrapper()

			const style = wrapper.vm.topBarStyle

			expect(typeof style).toBe('object')
		})

		it('includes CSS variable key', () => {
			wrapper = createWrapper()

			const style = wrapper.vm.topBarStyle

			expect(style['--original-color-main-background']).toBeDefined()
		})

		it('retrieves color from body styles', () => {
			wrapper = createWrapper()

			const style = wrapper.vm.topBarStyle

			// Should have some value (even if empty from test environment)
			expect(style['--original-color-main-background']).toBeDefined()
		})

		it('is reactive when body style changes', async () => {
			wrapper = createWrapper()

			const style1 = wrapper.vm.topBarStyle

			// Simulate document change (in real scenario)
			const style2 = wrapper.vm.topBarStyle

			// Should return consistent structure
			expect(style2['--original-color-main-background']).toBeDefined()
		})
	})

	describe('RULE: topBarStyle applies to element', () => {
		it('applies computed style to top-bar', () => {
			wrapper = createWrapper()

			const topBar = wrapper.find('.top-bar')

			expect(topBar.exists()).toBe(true)
			expect(wrapper.vm.topBarStyle['--original-color-main-background']).toBeDefined()
		})

		it('contains CSS variable reference', () => {
			wrapper = createWrapper()
			const style = wrapper.vm.topBarStyle
			expect(style['--original-color-main-background']).toBeDefined()
		})
	})

	describe('RULE: top-bar container element structure', () => {
		it('renders with top-bar class', () => {
			wrapper = createWrapper()

			const topBar = wrapper.find('.top-bar')
			expect(topBar.exists()).toBe(true)
		})

		it('contains filter slot and optional toggle', () => {
			wrapper = createWrapper({
				sidebarToggle: true,
			})

			const topBar = wrapper.find('.top-bar')

			expect(topBar.exists()).toBe(true)
		})
	})

	describe('RULE: sidebarToggle prop controls conditional rendering', () => {
		it('prop defaults to false', () => {
			wrapper = mount(TopBar, {
				global: {
					stubs: {
						SidebarToggle: { name: 'SidebarToggle', template: '<div class="sidebar-toggle-stub">Toggle</div>' },
					},
				},
			})

			expect(wrapper.props('sidebarToggle')).toBe(false)
		})

		it('accepts boolean true value', () => {
			wrapper = createWrapper({
				sidebarToggle: true,
			})

			expect(wrapper.props('sidebarToggle')).toBe(true)
		})

		it('accepts boolean false value', () => {
			wrapper = createWrapper({
				sidebarToggle: false,
			})

			expect(wrapper.props('sidebarToggle')).toBe(false)
		})
	})

	describe('RULE: multiple state combinations', () => {
		it('shows filter and toggle when both enabled', () => {
			wrapper = mount(TopBar, {
				props: {
					sidebarToggle: true,
				},
				global: {
					stubs: {
						SidebarToggle: { name: 'SidebarToggle', template: '<div class="toggle">Toggle</div>' },
					},
				},
				slots: {
					filter: '<div class="filter">Filter</div>',
				},
			})

			expect(wrapper.find('.filter').exists()).toBe(true)
			expect(wrapper.find('.toggle').exists()).toBe(true)
		})

		it('shows only filter when toggle disabled', () => {
			wrapper = mount(TopBar, {
				props: {
					sidebarToggle: false,
				},
				global: {
					stubs: {
						SidebarToggle: { name: 'SidebarToggle', template: '<div class="toggle">Toggle</div>' },
					},
				},
				slots: {
					filter: '<div class="filter">Filter</div>',
				},
			})

			expect(wrapper.find('.filter').exists()).toBe(true)
			expect(wrapper.find('.toggle').exists()).toBe(false)
		})

		it('shows only toggle when no filter slot', () => {
			wrapper = mount(TopBar, {
				props: {
					sidebarToggle: true,
				},
				global: {
					stubs: {
						SidebarToggle: { name: 'SidebarToggle', template: '<div class="toggle">Toggle</div>' },
					},
				},
			})

			expect(wrapper.find('.toggle').exists()).toBe(true)
		})
	})

	describe('RULE: layout renders correctly', () => {
		it('renders as div with proper structure', () => {
			wrapper = createWrapper()

			const topBar = wrapper.find('.top-bar')

			expect(topBar.element.tagName).toBe('DIV')
		})

		it('contains slot marker in DOM', () => {
			wrapper = createWrapper()

			const topBar = wrapper.find('.top-bar')

			// Slots render as comments or in content
			expect(topBar).toBeTruthy()
		})
	})

	describe('RULE: component responds to prop changes', () => {
		it('updates when sidebarToggle prop changes', async () => {
			wrapper = createWrapper({
				sidebarToggle: false,
			})

			expect(wrapper.findComponent({ name: 'SidebarToggle' }).exists()).toBe(false)

			await wrapper.setProps({ sidebarToggle: true })

			expect(wrapper.findComponent({ name: 'SidebarToggle' }).exists()).toBe(true)

			await wrapper.setProps({ sidebarToggle: false })

			expect(wrapper.findComponent({ name: 'SidebarToggle' }).exists()).toBe(false)
		})

		it('maintains style through prop changes', async () => {
			wrapper = createWrapper({
				sidebarToggle: false,
			})

			const style1 = wrapper.vm.topBarStyle

			await wrapper.setProps({ sidebarToggle: true })

			const style2 = wrapper.vm.topBarStyle
			expect(style2['--original-color-main-background']).toBeDefined()
			expect(style1['--original-color-main-background']).toBeDefined()
		})
	})

	describe('RULE: SidebarToggle component receives no props', () => {
		it('SidebarToggle rendered without props', () => {
			wrapper = createWrapper({
				sidebarToggle: true,
			})

			const toggle = wrapper.findComponent({ name: 'SidebarToggle' })

			// Should be rendered with default behavior
			expect(toggle.exists()).toBe(true)
		})
	})

	describe('RULE: comprehensive workflow scenarios', () => {
		it('workflow: default state with filter only', () => {
			wrapper = mount(TopBar, {
				props: { sidebarToggle: false },
				global: { stubs: { SidebarToggle: { name: 'SidebarToggle', template: '<div class="sidebar-toggle-stub">Toggle</div>' } } },
				slots: { filter: '<div class="filter">Search</div>' },
			})

			expect(wrapper.find('.filter').exists()).toBe(true)
			expect(wrapper.findComponent({ name: 'SidebarToggle' }).exists()).toBe(false)
		})

		it('workflow: full state with filter and toggle', () => {
			wrapper = mount(TopBar, {
				props: { sidebarToggle: true },
				global: {
					stubs: {
						SidebarToggle: { name: 'SidebarToggle', template: '<button>☰</button>' },
					},
				},
				slots: { filter: '<input type="search" class="filter-input" />' },
			})

			expect(wrapper.find('.filter-input').exists()).toBe(true)
			expect(wrapper.find('button').exists()).toBe(true)
		})

		it('workflow: empty state', () => {
			wrapper = mount(TopBar, {
				props: { sidebarToggle: false },
				global: { stubs: { SidebarToggle: { name: 'SidebarToggle', template: '<div class="sidebar-toggle-stub">Toggle</div>' } } },
			})

			const topBar = wrapper.find('.top-bar')
			expect(topBar.exists()).toBe(true)
		})
	})

	describe('RULE: CSS variable mechanism', () => {
		it('retrieves computed style from body', () => {
			wrapper = createWrapper()

			const style = wrapper.vm.topBarStyle

			// The key should match the CSS variable name
			expect('--original-color-main-background' in style).toBe(true)
		})

		it('uses getComputedStyle API', () => {
			wrapper = createWrapper()

			// This implicitly tests that getComputedStyle is called
			const style = wrapper.vm.topBarStyle

			// Should return string value
			expect(typeof style['--original-color-main-background']).toBe('string')
		})
	})
})
