/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import ConfigureCheck from '../../../views/Settings/ConfigureCheck.vue'

const useConfigureCheckStoreMock = vi.fn()

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('../../../store/configureCheck.js', () => ({
	useConfigureCheckStore: (...args: unknown[]) => useConfigureCheckStoreMock(...args),
}))

describe('ConfigureCheck.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	function createWrapper(items: Array<Record<string, string>>) {
		useConfigureCheckStoreMock.mockReturnValue({ items })

		return mount(ConfigureCheck, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcRichText: { props: ['text'], template: '<div class="rich-text">{{ text }}</div>' },
				},
			},
		})
	}

	it('renders nothing when there are no configuration items', () => {
		const wrapper = createWrapper([])

		expect(wrapper.find('table').exists()).toBe(false)
	})

	it('renders the configuration rows from the store', () => {
		const wrapper = createWrapper([
			{
				status: 'error',
				message: 'Missing Java runtime',
				resource: 'java',
				tip: 'Install Java',
			},
		])

		expect(wrapper.text()).toContain('Missing Java runtime')
		expect(wrapper.text()).toContain('java')
		expect(wrapper.text()).toContain('Install Java')
		expect(wrapper.find('td.error').exists()).toBe(true)
	})
})
