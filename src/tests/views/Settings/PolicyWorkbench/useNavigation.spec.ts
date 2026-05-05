/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useNavigation } from '../../../../views/Settings/PolicyWorkbench/Catalog/composables/useNavigation'

function createRect(top: number): DOMRect {
	return {
		top,
		left: 0,
		right: 0,
		bottom: top + 40,
		width: 100,
		height: 40,
		x: 0,
		y: top,
		toJSON: () => ({}),
	} as DOMRect
}

describe('useNavigation', () => {
	function createHarness() {
		const Harness = defineComponent({
			setup(_, { expose }) {
				const navigation = useNavigation({ value: [] })
				expose({ navigation })
				return () => h('div')
			},
		})

		return mount(Harness)
	}

	function getNavigation(wrapper: ReturnType<typeof createHarness>): ReturnType<typeof useNavigation> {
		return (wrapper.vm as unknown as { navigation: ReturnType<typeof useNavigation> }).navigation
	}

	beforeEach(() => {
		document.body.innerHTML = ''
		document.documentElement.style.setProperty('--header-height', '50px')
		Object.defineProperty(window, 'scrollTo', {
			value: vi.fn(),
			configurable: true,
		})
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('scrollToTop falls back to page top when toolbar is unavailable', () => {
		const wrapper = createHarness()
		const navigation = getNavigation(wrapper)

		navigation.catalogToolbarRef.value = null
		navigation.scrollToTop()

		expect(window.scrollTo).toHaveBeenCalledWith({
			top: 0,
			behavior: 'smooth',
		})

		wrapper.unmount()
	})

	it('scrollToTop targets search toolbar in window scroll mode and focuses input', () => {
		const wrapper = createHarness()
		const navigation = getNavigation(wrapper)
		const nativeQuerySelector = document.querySelector.bind(document)
		vi.spyOn(document, 'querySelector').mockImplementation((selector: string) => {
			if (selector === '#app-content') {
				return null
			}

			return nativeQuerySelector(selector)
		})

		const toolbar = document.createElement('div')
		const input = document.createElement('input')
		toolbar.appendChild(input)
		document.body.appendChild(toolbar)
		toolbar.getBoundingClientRect = vi.fn(() => createRect(300))

		document.documentElement.style.setProperty('--header-height', '60px')
		Object.defineProperty(window, 'scrollY', {
			get: () => 700,
			configurable: true,
		})

		navigation.catalogToolbarRef.value = toolbar
		navigation.scrollToTop()

		expect(window.scrollTo).toHaveBeenCalledTimes(1)
		expect(window.scrollTo).toHaveBeenCalledWith(expect.objectContaining({
			behavior: 'smooth',
		}))
		expect(document.activeElement).toBe(input)

		wrapper.unmount()
	})

	it('scrollToTop targets toolbar inside a scrollable app-content container', () => {
		const wrapper = createHarness()
		const navigation = getNavigation(wrapper)

		const appContent = document.createElement('div')
		appContent.id = 'app-content'
		Object.defineProperty(appContent, 'scrollHeight', { value: 1200, configurable: true })
		Object.defineProperty(appContent, 'clientHeight', { value: 500, configurable: true })
		Object.defineProperty(appContent, 'scrollTop', { value: 200, writable: true, configurable: true })
		Object.defineProperty(appContent, 'scrollTo', { value: vi.fn(), configurable: true })
		document.body.appendChild(appContent)

		const toolbar = document.createElement('div')
		const input = document.createElement('input')
		toolbar.appendChild(input)
		appContent.appendChild(toolbar)

		appContent.getBoundingClientRect = vi.fn(() => createRect(100))
		toolbar.getBoundingClientRect = vi.fn(() => createRect(250))

		navigation.catalogToolbarRef.value = toolbar
		navigation.scrollToTop()

		expect(appContent.scrollTo).toHaveBeenCalledWith({
			top: 338,
			behavior: 'smooth',
		})
		expect(window.scrollTo).not.toHaveBeenCalled()
		expect(document.activeElement).toBe(input)

		wrapper.unmount()
	})
})
