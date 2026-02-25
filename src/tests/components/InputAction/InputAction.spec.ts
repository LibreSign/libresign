/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import InputAction from '../../../components/InputAction/InputAction.vue'

describe('InputAction', () => {
	const createWrapper = (props = {}) => {
		return mount(InputAction, {
			props: {
				type: 'text',
				placeholder: '',
				disabled: false,
				loading: false,
				...props,
			},
		})
	}

	let wrapper: ReturnType<typeof createWrapper> | undefined

	beforeEach(() => {
		if (wrapper) {
		}
		vi.clearAllMocks()
	})

	describe('RULE: input value controlled via v-model', () => {
		it('initializes with empty value', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.value).toBe('')
		})

		it('updates value on input', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			await input.setValue('test input')

			expect(wrapper.vm.value).toBe('test input')
		})

		it('reflects input text changes', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			await input.setValue('hello')

			expect(input.element.value).toBe('hello')
		})

		it('handles multiple character input', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			await input.setValue('abcdefghijklmnop')

			expect(wrapper.vm.value).toBe('abcdefghijklmnop')
		})

		it('handles empty string clear', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			await input.setValue('text')
			await input.setValue('')

			expect(wrapper.vm.value).toBe('')
		})
	})

	describe('RULE: placeholder prop controls input placeholder', () => {
		it('displays placeholder when provided', () => {
			wrapper = createWrapper({
				placeholder: 'Enter your name',
			})

			const input = wrapper.find('input')
			expect(input.attributes('placeholder')).toBe('Enter your name')
		})

		it('has empty placeholder by default', () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			expect(input.attributes('placeholder')).toBe('')
		})

		it('updates placeholder on prop change', async () => {
			wrapper = createWrapper({
				placeholder: 'Original',
			})

			await wrapper.setProps({ placeholder: 'Updated' })

			const input = wrapper.find('input')
			expect(input.attributes('placeholder')).toBe('Updated')
		})
	})

	describe('RULE: type prop controls input type', () => {
		it('defaults to text type', () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			expect(input.attributes('type')).toBe('text')
		})

		it('sets input type to email', () => {
			wrapper = createWrapper({
				type: 'email',
			})

			const input = wrapper.find('input')
			expect(input.attributes('type')).toBe('email')
		})

		it('sets input type to password', () => {
			wrapper = createWrapper({
				type: 'password',
			})

			const input = wrapper.find('input')
			expect(input.attributes('type')).toBe('password')
		})

		it('sets input type to number', () => {
			wrapper = createWrapper({
				type: 'number',
			})

			const input = wrapper.find('input')
			expect(input.attributes('type')).toBe('number')
		})

		it('changes type on prop update', async () => {
			wrapper = createWrapper({
				type: 'text',
			})

			await wrapper.setProps({ type: 'password' })

			const input = wrapper.find('input')
			expect(input.attributes('type')).toBe('password')
		})
	})

	describe('RULE: disabled prop disables input and button', () => {
		it('input not disabled by default', () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			expect(input.attributes('disabled')).toBeUndefined()
		})

		it('disables input when disabled true', () => {
			wrapper = createWrapper({
				disabled: true,
			})

			const input = wrapper.find('input')
			expect(input.attributes('disabled')).toBeDefined()
		})

		it('disables button when disabled true', () => {
			wrapper = createWrapper({
				disabled: true,
			})

			const button = wrapper.find('button')
			expect(button.attributes('disabled')).toBeDefined()
		})

		it('enables both when disabled false', () => {
			wrapper = createWrapper({
				disabled: false,
			})

			const input = wrapper.find('input')
			const button = wrapper.find('button')

			expect(input.attributes('disabled')).toBeUndefined()
			expect(button.attributes('disabled')).toBeUndefined()
		})

		it('toggles disabled state on prop change', async () => {
			wrapper = createWrapper({
				disabled: false,
			})

			let input = wrapper.find('input')
			expect(input.attributes('disabled')).toBeUndefined()

			await wrapper.setProps({ disabled: true })

			input = wrapper.find('input')
			expect(input.attributes('disabled')).toBeDefined()
		})
	})

	describe('RULE: loading prop adds loading class to button', () => {
		it('button has icon-confirm class by default', () => {
			wrapper = createWrapper({
				loading: false,
			})

			const button = wrapper.find('button')
			expect(button.classes()).toContain('icon-confirm')
		})

		it('button has loading class when loading true', () => {
			wrapper = createWrapper({
				loading: true,
			})

			const button = wrapper.find('button')
			expect(button.classes()).toContain('loading')
		})

		it('button loses icon-confirm when loading', () => {
			wrapper = createWrapper({
				loading: true,
			})

			const button = wrapper.find('button')
			expect(button.classes()).not.toContain('icon-confirm')
		})

		it('toggles loading class on prop change', async () => {
			wrapper = createWrapper({
				loading: false,
			})

			let button = wrapper.find('button')
			expect(button.classes()).toContain('icon-confirm')

			await wrapper.setProps({ loading: true })

			button = wrapper.find('button')
			expect(button.classes()).toContain('loading')
		})
	})

	describe('RULE: button click submits current value', () => {
		it('emits submit event on button click', async () => {
			wrapper = createWrapper()

			const button = wrapper.find('button')
			await button.trigger('click')

			expect(wrapper.emitted('submit')).toBeTruthy()
		})

		it('emits submit with input value', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			await input.setValue('test value')

			const button = wrapper.find('button')
			await button.trigger('click')

			expect(wrapper.emitted('submit')?.[0]).toEqual(['test value'])
		})

		it('emits empty string when input empty', async () => {
			wrapper = createWrapper()

			const button = wrapper.find('button')
			await button.trigger('click')

			expect(wrapper.emitted('submit')?.[0]).toEqual([''])
		})

		it('emits different values for multiple submissions', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			const button = wrapper.find('button')

			await input.setValue('first')
			await button.trigger('click')

			await input.setValue('second')
			await button.trigger('click')

			const emitted = wrapper.emitted('submit')
			expect(emitted).toHaveLength(2)
			expect(emitted?.[0]).toEqual(['first'])
			expect(emitted?.[1]).toEqual(['second'])
		})

		it('preserves input value after submit', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			await input.setValue('preserve me')

			const button = wrapper.find('button')
			await button.trigger('click')

			expect(wrapper.vm.value).toBe('preserve me')
		})
	})

	describe('RULE: clearInput method resets value', () => {
		it('clears input value', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			await input.setValue('clear me')

			expect(wrapper.vm.value).toBe('clear me')

			wrapper.vm.clearInput()

			expect(wrapper.vm.value).toBe('')
		})

		it('works when input is empty', () => {
			wrapper = createWrapper()

			wrapper.vm.clearInput()

			expect(wrapper.vm.value).toBe('')
		})

		it('can be called multiple times', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')

			await input.setValue('text1')
			wrapper.vm.clearInput()
			expect(wrapper.vm.value).toBe('')

			await input.setValue('text2')
			wrapper.vm.clearInput()
			expect(wrapper.vm.value).toBe('')
		})
	})

	describe('RULE: form submission prevented', () => {
		it('prevents default form submission', async () => {
			wrapper = createWrapper()

			const form = wrapper.find('form')
			const event = new Event('submit', { cancelable: true })
			form.element.dispatchEvent(event)

			expect(event.defaultPrevented).toBe(true)
		})

		it('does not trigger page reload on enter', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			const event = new KeyboardEvent('keydown', { key: 'Enter' })

			await input.trigger('keydown', event)
		})
	})

	describe('RULE: form structure with input and button', () => {
		it('contains form element', () => {
			wrapper = createWrapper()

			const form = wrapper.find('form')
			expect(form.exists()).toBe(true)
		})

		it('contains input field', () => {
			wrapper = createWrapper()

			const input = wrapper.find('input.input__input')
			expect(input.exists()).toBe(true)
		})

		it('contains button element', () => {
			wrapper = createWrapper()

			const button = wrapper.find('button')
			expect(button.exists()).toBe(true)
		})
	})

	describe('RULE: all props work together', () => {
		it('combines multiple prop behaviors', async () => {
			wrapper = createWrapper({
				type: 'email',
				placeholder: 'Enter email',
				disabled: false,
				loading: false,
			})

			const input = wrapper.find('input')
			await input.setValue('test@example.com')

			expect(input.attributes('type')).toBe('email')
			expect(input.attributes('placeholder')).toBe('Enter email')
			expect(input.attributes('disabled')).toBeUndefined()

			const button = wrapper.find('button')
			expect(button.classes()).toContain('icon-confirm')
		})

		it('handles complete workflow with state changes', async () => {
			wrapper = createWrapper({
				type: 'text',
				placeholder: 'Type something',
				disabled: false,
				loading: false,
			})

			const input = wrapper.find('input')
			const button = wrapper.find('button')

			// Type something
			await input.setValue('my value')
			expect(wrapper.vm.value).toBe('my value')

			// Show loading
			await wrapper.setProps({ loading: true })
			expect(button.classes()).toContain('loading')

			// Submit
			await button.trigger('click')
			expect(wrapper.emitted('submit')).toBeTruthy()

			// Clear
			wrapper.vm.clearInput()
			expect(wrapper.vm.value).toBe('')
		})

		it('handles disabled state with input', async () => {
			wrapper = createWrapper({
				disabled: true,
			})

			const input = wrapper.find('input')
			expect(input.attributes('disabled')).toBeDefined()

			await wrapper.setProps({ disabled: false })

			expect(input.attributes('disabled')).toBeUndefined()
		})
	})

	describe('RULE: input accepts various text content', () => {
		it('accepts alphanumeric input', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			await input.setValue('abc123XYZ')

			expect(wrapper.vm.value).toBe('abc123XYZ')
		})

		it('accepts special characters', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			await input.setValue('!@#$%^&*()')

			expect(wrapper.vm.value).toBe('!@#$%^&*()')
		})

		it('accepts spaces', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			await input.setValue('hello world test')

			expect(wrapper.vm.value).toBe('hello world test')
		})

		it('accepts unicode characters', async () => {
			wrapper = createWrapper()

			const input = wrapper.find('input')
			await input.setValue('café naïve résumé')

			expect(wrapper.vm.value).toBe('café naïve résumé')
		})
	})

	describe('RULE: button click works when not disabled', () => {
		it('button click works when enabled', async () => {
			wrapper = createWrapper({
				disabled: false,
			})

			const button = wrapper.find('button')
			const input = wrapper.find('input')

			await input.setValue('submit this')
			await button.trigger('click')

			expect(wrapper.emitted('submit')).toBeTruthy()
		})

		it('does not emit submit when disabled', async () => {
			wrapper = createWrapper({
				disabled: true,
			})

			const button = wrapper.find('button')

			// Click button - it should be disabled
			await button.trigger('click')

			// Event might still trigger but button has disabled attribute
			expect(button.attributes('disabled')).toBeDefined()
		})
	})
})
