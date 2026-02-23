/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@vue/test-utils', async (importOriginal) => {
	const actual = await importOriginal()
	const wrap = (fn) => (...args) => {
		if (typeof fn !== 'function') {
			return fn
		}
		const wrapper = fn(...args)
		if (wrapper && !wrapper.destroy && typeof wrapper.unmount === 'function') {
			wrapper.destroy = wrapper.unmount
		}
		return wrapper
	}

	return {
		...actual,
		mount: wrap(actual.mount),
		shallowMount: wrap(actual.shallowMount),
	}
})

vi.mock('vue-select', () => ({
	default: {
		name: 'VueSelect',
		render() {
			return null
		},
	},
}))

vi.mock('vue-select/dist/vue-select.es.js', () => ({
	default: {
		name: 'VueSelect',
		render() {
			return null
		},
	},
}))

vi.mock('@nextcloud/vue/components/NcSelect', () => ({
	default: {
		name: 'NcSelect',
		template: '<div></div>',
	},
}))


vi.mock('@nextcloud/vue/components/NcRichText', () => ({
	default: {
		name: 'NcRichText',
		template: '<div></div>',
	},
}))


setActivePinia(createPinia())

import './testHelpers/jsdomMocks.js'
import './testHelpers/nextcloudMocks.js'
import './testHelpers/vueMocks.js'
