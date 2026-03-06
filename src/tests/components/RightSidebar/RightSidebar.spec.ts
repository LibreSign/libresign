/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import RightSidebar from '../../../components/RightSidebar/RightSidebar.vue'

const filesStoreMock = {
	getFile: vi.fn(() => ({ name: 'agreement.pdf' })),
	getSubtitle: vi.fn(() => 'Alice, Bob'),
}

const sidebarStoreMock = {
	activeTab: 'request-signature-tab',
	isVisible: true,
	setActiveTab: vi.fn(),
	hideSidebar: vi.fn(),
	handleRouteChange: vi.fn(),
}

const signStoreMock = {}

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('vue-router', () => ({
	useRoute: vi.fn(() => ({
		name: 'Request',
	})),
}))

vi.mock('../../../store/files.js', () => ({
	useFilesStore: vi.fn(() => filesStoreMock),
}))

vi.mock('../../../store/sidebar.js', () => ({
	useSidebarStore: vi.fn(() => sidebarStoreMock),
}))

vi.mock('../../../store/sign.js', () => ({
	useSignStore: vi.fn(() => signStoreMock),
}))

vi.mock('../../../components/RightSidebar/RequestSignatureTab.vue', () => ({
	default: {
		name: 'RequestSignatureTab',
		template: '<div class="request-signature-tab"></div>',
	},
}))

vi.mock('../../../components/RightSidebar/SignTab.vue', () => ({
	default: {
		name: 'SignTab',
		template: '<div class="sign-tab"></div>',
	},
}))

describe('RightSidebar.vue', () => {
	beforeEach(() => {
		filesStoreMock.getFile.mockReturnValue({ name: 'agreement.pdf' })
		filesStoreMock.getSubtitle.mockReturnValue('Alice, Bob')
		sidebarStoreMock.activeTab = 'request-signature-tab'
		sidebarStoreMock.isVisible = true
		sidebarStoreMock.setActiveTab.mockReset()
		sidebarStoreMock.hideSidebar.mockReset()
		sidebarStoreMock.handleRouteChange.mockReset()
	})

	function createWrapper() {
		return mount(RightSidebar, {
			global: {
				stubs: {
					NcAppSidebar: {
						name: 'NcAppSidebar',
						template: '<div class="app-sidebar"><slot /></div>',
						props: ['open', 'name', 'subtitle', 'active'],
						emits: ['update:active', 'close'],
					},
					NcAppSidebarTab: {
						name: 'NcAppSidebarTab',
						template: '<div class="app-sidebar-tab"><slot /></div>',
						props: ['id', 'name'],
					},
				},
			},
		})
	}

	it('renders the request signature tab when active', () => {
		const wrapper = createWrapper()

		expect(wrapper.find('.app-sidebar').exists()).toBe(true)
		expect(wrapper.find('.request-signature-tab').exists()).toBe(true)
		expect(wrapper.find('.sign-tab').exists()).toBe(false)
	})

	it('renders the sign tab when selected', () => {
		sidebarStoreMock.activeTab = 'sign-tab'
		const wrapper = createWrapper()

		expect(wrapper.find('.sign-tab').exists()).toBe(true)
		expect(wrapper.find('.request-signature-tab').exists()).toBe(false)
	})

	it('forwards active tab updates and close events to the sidebar store', async () => {
		const wrapper = createWrapper()
		const sidebar = wrapper.findComponent({ name: 'NcAppSidebar' })

		await sidebar.vm.$emit('update:active', 'sign-tab')
		await sidebar.vm.$emit('close')

		expect(sidebarStoreMock.setActiveTab).toHaveBeenCalledWith('sign-tab')
		expect(sidebarStoreMock.hideSidebar).toHaveBeenCalledTimes(1)
	})
})
