/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import EditNameDialog from '../../../components/Common/EditNameDialog.vue'
import { ENVELOPE_NAME_MIN_LENGTH, ENVELOPE_NAME_MAX_LENGTH } from '../../../constants.js'

describe('EditNameDialog.vue - Business Logic', () => {
	type DialogButton = {
		type?: string
		variant?: string
		disabled?: boolean
		callback: () => void
	}

	type EditNameDialogVm = {
		localName: string
		localSuccessMessage: string
		localErrorMessage: string
		isNameValid: boolean
		dialogButtons: DialogButton[]
		ENVELOPE_NAME_MIN_LENGTH: number
		ENVELOPE_NAME_MAX_LENGTH: number
		handleSave: () => void
		handleClose: () => void
		showSuccess: (message: string) => void
		showError: (message: string) => void
		clearMessages: () => void
		$nextTick: () => Promise<void>
	}

	type EditNameDialogWrapper = VueWrapper<any> & {
		vm: EditNameDialogVm
	}

	let wrapper: EditNameDialogWrapper

	const createWrapper = (name = 'Initial Name') => shallowMount(EditNameDialog, {
		props: {
			name,
			title: 'Edit Name',
			label: 'Name',
			placeholder: 'Enter name',
		},
		stubs: {
			NcButton: true,
			NcDialog: true,
			NcNoteCard: true,
			NcTextField: true,
		},
	}) as EditNameDialogWrapper

	const setLocalName = async (value: string) => {
		wrapper.vm.localName = value
		await wrapper.vm.$nextTick()
	}

	const setMessages = async ({ success = wrapper.vm.localSuccessMessage, error = wrapper.vm.localErrorMessage }: { success?: string, error?: string }) => {
		wrapper.vm.localSuccessMessage = success
		wrapper.vm.localErrorMessage = error
		await wrapper.vm.$nextTick()
	}

	beforeEach(() => {
		wrapper = createWrapper()
	})

	describe('isNameValid computed property', () => {
		it('returns true for valid name within limits', async () => {
			await setLocalName('Valid Name')
			expect(wrapper.vm.isNameValid).toBe(true)
		})

		it('returns false for empty string', async () => {
			await setLocalName('')
			expect(wrapper.vm.isNameValid).toBe(false)
		})

		it('returns false for whitespace-only string', async () => {
			await setLocalName('   ')
			expect(wrapper.vm.isNameValid).toBe(false)
		})

		it('returns true for name with exactly min length after trim', async () => {
			await setLocalName('  A  ')
			expect(wrapper.vm.isNameValid).toBe(true)
		})

		it('returns true for name with exactly max length', async () => {
			const maxLengthName = 'A'.repeat(ENVELOPE_NAME_MAX_LENGTH)
			await setLocalName(maxLengthName)
			expect(wrapper.vm.isNameValid).toBe(true)
		})

		it('returns false for name exceeding max length', async () => {
			const tooLongName = 'A'.repeat(ENVELOPE_NAME_MAX_LENGTH + 1)
			await setLocalName(tooLongName)
			expect(wrapper.vm.isNameValid).toBe(false)
		})

		it('returns true for name with leading/trailing spaces but valid after trim', async () => {
			await setLocalName('  Valid Name  ')
			expect(wrapper.vm.isNameValid).toBe(true)
		})
	})

	describe('dialogButtons computed property', () => {
		it('disables Save button when name is invalid', async () => {
			await setLocalName('')
			const buttons = wrapper.vm.dialogButtons as DialogButton[]
			const saveButton = buttons.find((btn) => btn.variant === 'primary')
			expect(saveButton).toBeDefined()
			expect(saveButton!.disabled).toBe(true)
		})

		it('enables Save button when name is valid', async () => {
			await setLocalName('Valid Name')
			const buttons = wrapper.vm.dialogButtons as DialogButton[]
			const saveButton = buttons.find((btn) => btn.variant === 'primary')
			expect(saveButton).toBeDefined()
			expect(saveButton!.disabled).toBe(false)
		})

		it('has Cancel button that closes with null', () => {
			const buttons = wrapper.vm.dialogButtons as DialogButton[]
			const cancelButton = buttons.find((btn) => btn.variant !== 'primary')
			expect(cancelButton).toBeDefined()
			cancelButton!.callback()
			expect(wrapper.emitted('close')?.[0]).toEqual([null])
		})

		it('has Save button that emits the trimmed name when valid', async () => {
			await setLocalName('  Valid Name  ')
			const buttons = wrapper.vm.dialogButtons as DialogButton[]
			const saveButton = buttons.find((btn) => btn.variant === 'primary')
			expect(saveButton).toBeDefined()
			saveButton!.callback()
			expect(wrapper.emitted('close')?.[0]).toEqual(['Valid Name'])
		})
	})

	describe('handleSave method', () => {
		it('does not emit when name is invalid', async () => {
			await setLocalName('')
			wrapper.vm.handleSave()
			expect(wrapper.emitted('close')).toBeUndefined()
		})

		it('emits close with trimmed name when valid', async () => {
			await setLocalName('  Valid Name  ')
			wrapper.vm.handleSave()
			expect(wrapper.emitted('close')).toBeTruthy()
			expect(wrapper.emitted('close')?.[0]).toEqual(['Valid Name'])
		})

		it('does not emit when trimmed name is empty (handled by isNameValid)', async () => {
			await setLocalName('   ')
			wrapper.vm.handleSave()
			// isNameValid already catches this case, so handleSave returns early
			expect(wrapper.emitted('close')).toBeUndefined()
		})

		it('does not emit when name is empty (handled by isNameValid)', async () => {
			await setLocalName('')
			wrapper.vm.handleSave()
			// isNameValid already catches this case, so handleSave returns early
			expect(wrapper.emitted('close')).toBeUndefined()
		})

		it('emits with single character name (minimum valid)', async () => {
			await setLocalName('A')
			wrapper.vm.handleSave()
			expect(wrapper.emitted('close')).toBeTruthy()
			expect(wrapper.emitted('close')?.[0]).toEqual(['A'])
		})

		it('emits with max length name', async () => {
			const maxName = 'A'.repeat(ENVELOPE_NAME_MAX_LENGTH)
			await setLocalName(maxName)
			wrapper.vm.handleSave()
			expect(wrapper.emitted('close')).toBeTruthy()
			expect(wrapper.emitted('close')?.[0]).toEqual([maxName])
		})

		it('preserves internal spaces in name', async () => {
			await setLocalName('  Name With   Spaces  ')
			wrapper.vm.handleSave()
			expect(wrapper.emitted('close')).toBeTruthy()
			expect(wrapper.emitted('close')?.[0]).toEqual(['Name With   Spaces'])
		})
	})

	describe('handleClose method', () => {
		it('emits close with null', () => {
			wrapper.vm.handleClose()
			expect(wrapper.emitted('close')).toBeTruthy()
			expect(wrapper.emitted('close')?.[0]).toEqual([null])
		})
	})

	describe('showSuccess method', () => {
		it('sets success message and clears error message', async () => {
			await setMessages({ error: 'Previous error' })
			wrapper.vm.showSuccess('Success!')
			expect(wrapper.vm.localSuccessMessage).toBe('Success!')
			expect(wrapper.vm.localErrorMessage).toBe('')
		})

		it('clears previous success message', async () => {
			await setMessages({ success: 'Old success' })
			wrapper.vm.showSuccess('New success')
			expect(wrapper.vm.localSuccessMessage).toBe('New success')
		})

		it('auto-clears success message after timeout', async () => {
			vi.useFakeTimers()
			wrapper.vm.showSuccess('Success!')
			expect(wrapper.vm.localSuccessMessage).toBe('Success!')

			vi.advanceTimersByTime(5000)
			expect(wrapper.vm.localSuccessMessage).toBe('')

			vi.useRealTimers()
		})

		it('does not clear success message before timeout', async () => {
			vi.useFakeTimers()
			wrapper.vm.showSuccess('Success!')
			expect(wrapper.vm.localSuccessMessage).toBe('Success!')

			vi.advanceTimersByTime(4999)
			expect(wrapper.vm.localSuccessMessage).toBe('Success!')

			vi.useRealTimers()
		})
	})

	describe('showError method', () => {
		it('sets error message and clears success message', async () => {
			await setMessages({ success: 'Previous success' })
			wrapper.vm.showError('Error!')
			expect(wrapper.vm.localErrorMessage).toBe('Error!')
			expect(wrapper.vm.localSuccessMessage).toBe('')
		})

		it('clears previous error message', async () => {
			await setMessages({ error: 'Old error' })
			wrapper.vm.showError('New error')
			expect(wrapper.vm.localErrorMessage).toBe('New error')
		})

		it('does not auto-clear error message', async () => {
			vi.useFakeTimers()
			wrapper.vm.showError('Error!')
			expect(wrapper.vm.localErrorMessage).toBe('Error!')

			vi.advanceTimersByTime(10000)
			expect(wrapper.vm.localErrorMessage).toBe('Error!')

			vi.useRealTimers()
		})
	})

	describe('clearMessages method', () => {
		it('clears both success and error messages', async () => {
			await setMessages({ success: 'Success', error: 'Error' })
			wrapper.vm.clearMessages()
			expect(wrapper.vm.localSuccessMessage).toBe('')
			expect(wrapper.vm.localErrorMessage).toBe('')
		})
	})

	describe('watch name', () => {
		it('updates localName when name prop changes', async () => {
			await wrapper.setProps({ name: 'New Name' })
			expect(wrapper.vm.localName).toBe('New Name')
		})

		it('sets empty string when name prop becomes empty', async () => {
			await wrapper.setProps({ name: '' })
			expect(wrapper.vm.localName).toBe('')
		})

		it('sets empty string when name prop becomes null', async () => {
			await wrapper.setProps({ name: null })
			expect(wrapper.vm.localName).toBe('')
		})

		it('sets empty string when name prop becomes undefined', async () => {
			await wrapper.setProps({ name: undefined })
			expect(wrapper.vm.localName).toBe('')
		})
	})

	describe('constants validation', () => {
		it('uses correct minimum length constant', () => {
			expect(wrapper.vm.ENVELOPE_NAME_MIN_LENGTH).toBe(1)
		})

		it('uses correct maximum length constant', () => {
			expect(wrapper.vm.ENVELOPE_NAME_MAX_LENGTH).toBe(255)
		})
	})

	describe('integration scenarios', () => {
		it('handles complete edit flow: open with name, edit, and save', async () => {
			wrapper = createWrapper('Original Name')

			expect(wrapper.vm.localName).toBe('Original Name')

			await setLocalName('Edited Name')
			wrapper.vm.handleSave()

			expect(wrapper.emitted('close')).toBeTruthy()
			expect(wrapper.emitted('close')?.[0]).toEqual(['Edited Name'])
		})

		it('handles cancel flow without changes', () => {
			wrapper = createWrapper('Original Name')

			wrapper.vm.handleClose()

			expect(wrapper.emitted('close')).toBeTruthy()
			expect(wrapper.emitted('close')?.[0]).toEqual([null])
		})

		it('handles edge case: exactly at boundary lengths', async () => {
			const minName = 'A'.repeat(ENVELOPE_NAME_MIN_LENGTH)
			await setLocalName(minName)
			expect(wrapper.vm.isNameValid).toBe(true)

			const maxName = 'B'.repeat(ENVELOPE_NAME_MAX_LENGTH)
			await setLocalName(maxName)
			expect(wrapper.vm.isNameValid).toBe(true)

			const tooLong = 'C'.repeat(ENVELOPE_NAME_MAX_LENGTH + 1)
			await setLocalName(tooLong)
			expect(wrapper.vm.isNameValid).toBe(false)
		})
	})
})
