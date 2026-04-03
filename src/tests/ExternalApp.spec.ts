/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import { initialActionCode, ACTION_CODES } from '../helpers/ActionMapping'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

import ExternalApp from '../ExternalApp.vue'

describe('ExternalApp', () => {
	beforeEach(() => {
		initialActionCode.value = 0
	})

	it('shows router-view and RightSidebar on normal load', () => {
		const wrapper = mount(ExternalApp, {
			global: {
				stubs: {
					DefaultPageError: { name: 'DefaultPageError', template: '<div class="default-page-error" />' },
					RightSidebar: { name: 'RightSidebar', template: '<aside class="right-sidebar" />' },
					RouterView: { template: '<div class="router-view" />' },
				},
			},
		})

		expect(wrapper.find('.router-view').exists()).toBe(true)
		expect(wrapper.find('.right-sidebar').exists()).toBe(true)
		expect(wrapper.find('.default-page-error').exists()).toBe(false)
	})

	it('shows DefaultPageError and hides RightSidebar when DO_NOTHING action is set', () => {
		initialActionCode.value = ACTION_CODES.DO_NOTHING

		const wrapper = mount(ExternalApp, {
			global: {
				stubs: {
					DefaultPageError: { name: 'DefaultPageError', template: '<div class="default-page-error" />' },
					RightSidebar: { name: 'RightSidebar', template: '<aside class="right-sidebar" />' },
					RouterView: { template: '<div class="router-view" />' },
				},
			},
		})

		expect(wrapper.find('.default-page-error').exists()).toBe(true)
		expect(wrapper.find('.right-sidebar').exists()).toBe(false)
		expect(wrapper.find('.router-view').exists()).toBe(false)
	})
})
