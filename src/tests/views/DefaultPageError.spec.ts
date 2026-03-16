/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi, type MockedFunction } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app: string, key: string, defaultValue: unknown) => defaultValue),
}))

vi.mock('@nextcloud/vue/components/NcEmptyContent', () => ({
	default: {
		name: 'NcEmptyContent',
		template: '<div class="nc-empty-content"><slot name="action" /><span class="title">{{ name }}</span><span class="description">{{ description }}</span></div>',
		props: ['name', 'description'],
	},
}))
vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({
	default: { name: 'NcIconSvgWrapper', template: '<span />', props: ['path', 'size'] },
}))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({
	default: { name: 'NcNoteCard', template: '<div class="nc-note-card"><slot /></div>', props: ['type'] },
}))

import DefaultPageError from '../../views/DefaultPageError.vue'

describe('DefaultPageError', () => {
	let loadState: MockedFunction<typeof import('@nextcloud/initial-state').loadState>

	beforeEach(async () => {
		const { loadState: ls } = await import('@nextcloud/initial-state')
		loadState = ls as MockedFunction<typeof import('@nextcloud/initial-state').loadState>
		loadState.mockImplementation((_app, _key, defaultValue) => defaultValue)
	})

	it('shows "An error occurred" title and error message when errors are provided via initial state', () => {
		loadState.mockImplementation((app, key, defaultValue) => {
			if (app === 'libresign' && key === 'errors') {
				return [{ message: 'This document is not yours. Log out and use the sign link again.' }]
			}
			return defaultValue
		})

		const wrapper = mount(DefaultPageError)

		expect(wrapper.find('.title').text()).toBe('An error occurred')
		expect(wrapper.find('.description').text()).toBe('')
		expect(wrapper.find('.nc-note-card').text()).toContain('This document is not yours. Log out and use the sign link again.')
	})

	it('shows "Page not found" title and description when no errors are present', () => {
		const wrapper = mount(DefaultPageError)

		expect(wrapper.find('.title').text()).toBe('Page not found')
		expect(wrapper.find('.description').text()).toContain('Sorry but the page you are looking for')
		expect(wrapper.find('.nc-note-card').exists()).toBe(false)
	})

	it('shows error message from "error" key when "errors" array is empty', () => {
		loadState.mockImplementation((app, key, defaultValue) => {
			if (app === 'libresign' && key === 'errors') return []
			if (app === 'libresign' && key === 'error') return { message: 'Something went wrong' }
			return defaultValue
		})

		const wrapper = mount(DefaultPageError)

		expect(wrapper.find('.title').text()).toBe('An error occurred')
		expect(wrapper.find('.nc-note-card').text()).toContain('Something went wrong')
	})
})
