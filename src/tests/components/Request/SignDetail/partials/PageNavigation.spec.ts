/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import PageNavigation from '../../../../../components/Request/SignDetail/partials/PageNavigation.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

describe('PageNavigation.vue', () => {
	function createWrapper(modelValue = 2) {
		return mount(PageNavigation, {
			props: {
				modelValue,
				pages: [1, 2, 3],
				width: '240px',
			},
			global: {
				stubs: {
					NcCounterBubble: { template: '<div><slot /></div>' },
				},
			},
		})
	}

	it('computes navigation state from the current page', () => {
		const wrapper = createWrapper()

		expect(wrapper.vm.size).toBe(3)
		expect(wrapper.vm.actual).toBe(2)
		expect(wrapper.vm.allowPrevious).toBe(true)
		expect(wrapper.vm.allowNext).toBe(true)
	})

	it('emits the next page when next is called', () => {
		const wrapper = createWrapper()

		wrapper.vm.next()

		expect(wrapper.emitted('update:modelValue')).toEqual([[3]])
	})

	it('emits the previous page when previous is called', () => {
		const wrapper = createWrapper()

		wrapper.vm.previous()

		expect(wrapper.emitted('update:modelValue')).toEqual([[1]])
	})

	it('disables previous navigation on the first page', () => {
		const wrapper = createWrapper(1)

		expect(wrapper.vm.allowPrevious).toBe(false)
		expect(wrapper.vm.allowNext).toBe(true)
	})
})
