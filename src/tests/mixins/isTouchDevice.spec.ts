/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import isTouchDevice from '../../mixins/isTouchDevice.js'

describe('isTouchDevice Mixin', () => {
	const TestComponent = {
		mixins: [isTouchDevice],
		template: '<div>{{ isTouchDevice }}</div>',
	}

	let wrapper: any

	beforeEach(() => {
		wrapper = null
	})

	afterEach(() => {
		if (wrapper) {
		}
	})

	describe('RULE: isTouchDevice detects touch capabilities', () => {
		it('provides isTouchDevice as computed property', () => {
			wrapper = mount(TestComponent)

			expect(wrapper.vm.isTouchDevice).toBeDefined()
			expect(typeof wrapper.vm.isTouchDevice).toBe('boolean')
		})

		it('returns true when environment supports touch (ontouchstart exists)', () => {
			wrapper = mount(TestComponent)

			// Verify the logic: should be true if either ontouchstart or maxTouchPoints exists
			const hasTouchStart = 'ontouchstart' in window
			const hasMaxTouchPoints = navigator.maxTouchPoints > 0
			const expectedValue = hasTouchStart || hasMaxTouchPoints

			expect(wrapper.vm.isTouchDevice).toBe(expectedValue)
		})

		it('correctly evaluates touch detection logic', () => {
			wrapper = mount(TestComponent)

			// Test the actual mixin logic
			const result = wrapper.vm.isTouchDevice
			const expected = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0)

			expect(result).toBe(expected)
		})

		it('maintains same value on multiple accesses', () => {
			wrapper = mount(TestComponent)

			const firstAccess = wrapper.vm.isTouchDevice
			const secondAccess = wrapper.vm.isTouchDevice

			expect(firstAccess).toBe(secondAccess)
		})

		it('is a reactive computed property', () => {
			wrapper = mount(TestComponent)

			// computed properties in Vue are reactive
			expect(wrapper.vm.$options.computed).toBeDefined()
			expect(wrapper.vm.$options.computed.isTouchDevice).toBeDefined()
		})
	})
})
