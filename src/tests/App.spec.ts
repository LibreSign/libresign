/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

type RouteState = {
	path: string
	name: string | undefined
	params: Record<string, unknown>
	matched: Array<{ meta?: Record<string, unknown> }>
}

const routeState: RouteState = {
	path: '/f/filelist/sign',
	name: 'fileslist',
	params: {},
	matched: [] as Array<{ meta?: Record<string, unknown> }>,
}

vi.mock('vue-router', async () => {
	const actual = await vi.importActual<typeof import('vue-router')>('vue-router')
	return {
		...actual,
		useRoute: () => routeState,
	}
})

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	isRTL: vi.fn(() => false),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
}))

import App from '../App.vue'

describe('App', () => {
	beforeEach(() => {
		routeState.path = '/f/filelist/sign'
		routeState.name = 'fileslist'
		routeState.params = {}
		routeState.matched = []
	})

	it('shows left sidebar on regular internal routes', () => {
		const wrapper = mount(App, {
			global: {
				stubs: {
					NcContent: { template: '<div><slot /></div>' },
					NcAppContent: { template: '<main><slot /></main>' },
					NcEmptyContent: { template: '<div><slot /></div>' },
					LeftSidebar: { name: 'LeftSidebar', template: '<aside class="left-sidebar" />' },
					RightSidebar: true,
					DefaultPageError: true,
					RouterView: { template: '<div class="router-view" />' },
				},
			},
		})

		expect(wrapper.find('.left-sidebar').exists()).toBe(true)
	})

	it('hides left sidebar on incomplete setup routes', () => {
		routeState.path = '/f/incomplete'
		routeState.name = 'Incomplete'
		routeState.matched = [{ meta: { hideLeftSidebar: true } }]

		const wrapper = mount(App, {
			global: {
				stubs: {
					NcContent: { template: '<div><slot /></div>' },
					NcAppContent: { template: '<main><slot /></main>' },
					NcEmptyContent: { template: '<div><slot /></div>' },
					LeftSidebar: { name: 'LeftSidebar', template: '<aside class="left-sidebar" />' },
					RightSidebar: true,
					DefaultPageError: true,
					RouterView: { template: '<div class="router-view" />' },
				},
			},
		})

		expect(wrapper.find('.left-sidebar').exists()).toBe(false)
	})

	it('hides left sidebar on incomplete external route name', () => {
		routeState.path = '/p/incomplete'
		routeState.name = 'IncompleteExternal'
		routeState.matched = [{ meta: { hideLeftSidebar: true } }]

		const wrapper = mount(App, {
			global: {
				stubs: {
					NcContent: { template: '<div><slot /></div>' },
					NcAppContent: { template: '<main><slot /></main>' },
					NcEmptyContent: { template: '<div><slot /></div>' },
					LeftSidebar: { name: 'LeftSidebar', template: '<aside class="left-sidebar" />' },
					RightSidebar: true,
					DefaultPageError: true,
					RouterView: { template: '<div class="router-view" />' },
				},
			},
		})

		expect(wrapper.find('.left-sidebar').exists()).toBe(false)
	})

	it('hides left sidebar on incomplete path before route name resolves', () => {
		routeState.path = '/f/incomplete'
		routeState.name = undefined
		routeState.matched = [{ meta: { hideLeftSidebar: true } }]

		const wrapper = mount(App, {
			global: {
				stubs: {
					NcContent: { template: '<div><slot /></div>' },
					NcAppContent: { template: '<main><slot /></main>' },
					NcEmptyContent: { template: '<div><slot /></div>' },
					LeftSidebar: { name: 'LeftSidebar', template: '<aside class="left-sidebar" />' },
					RightSidebar: true,
					DefaultPageError: true,
					RouterView: { template: '<div class="router-view" />' },
				},
			},
		})

		expect(wrapper.find('.left-sidebar').exists()).toBe(false)
	})
})
