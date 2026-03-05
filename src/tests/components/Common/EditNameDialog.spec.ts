/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import EditNameDialog from '../../../components/Common/EditNameDialog.vue'
import { ENVELOPE_NAME_MIN_LENGTH, ENVELOPE_NAME_MAX_LENGTH } from '../../../constants.js'

describe('EditNameDialog.vue - Business Logic', () => {
	type DialogButton = {
		type?: string
		disabled?: boolean
		callback: () => void
	}

	let wrapper: ReturnType<typeof shallowMount>

	beforeEach(() => {
		wrapper = shallowMount(EditNameDialog, {
			props: {
				name: 'Initial Name',
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
		})
	})

	describe('isNameValid computed property', () => {
		it('returns true for valid name within limits', () => {
			wrapper.setData({ localName: 'Valid Name' })
			expect(wrapper.vm.isNameValid).toBe(true)
		})

		it('returns false for empty string', () => {
			wrapper.setData({ localName: '' })
			expect(wrapper.vm.isNameValid).toBe(false)
		})

		it('returns false for whitespace-only string', () => {
			wrapper.setData({ localName: '   ' })
			expect(wrapper.vm.isNameValid).toBe(false)
		})

		it('returns true for name with exactly min length after trim', () => {
			wrapper.setData({ localName: '  A  ' }) // trims to 'A' which is length 1
			expect(wrapper.vm.isNameValid).toBe(true)
		})

		it('returns true for name with exactly max length', () => {
			const maxLengthName = 'A'.repeat(ENVELOPE_NAME_MAX_LENGTH)
			wrapper.setData({ localName: maxLengthName })
			expect(wrapper.vm.isNameValid).toBe(true)
		})

		it('returns false for name exceeding max length', () => {
			const tooLongName = 'A'.repeat(ENVELOPE_NAME_MAX_LENGTH + 1)
			wrapper.setData({ localName: tooLongName })
			expect(wrapper.vm.isNameValid).toBe(false)
		})

		it('returns true for name with leading/trailing spaces but valid after trim', () => {
			wrapper.setData({ localName: '  Valid Name  ' })
			expect(wrapper.vm.isNameValid).toBe(true)
		})
	})

	describe('dialogButtons computed property', () => {
		it('disables Save button when name is invalid', () => {
			wrapper.setData({ localName: '' })
			const buttons = wrapper.vm.dialogButtons as DialogButton[]
			const saveButton = buttons.find((btn) => btn.type === 'primary')
			expect(saveButton).toBeDefined()
			expect(saveButton!.disabled).toBe(true)
		})

		it('enables Save button when name is valid', () => {
			wrapper.setData({ localName: 'Valid Name' })
			const buttons = wrapper.vm.dialogButtons as DialogButton[]
			const saveButton = buttons.find((btn) => btn.type === 'primary')
			expect(saveButton).toBeDefined()
			expect(saveButton!.disabled).toBe(false)
		})

		it('has Cancel button that calls handleClose', () => {
			const handleCloseSpy = vi.spyOn(wrapper.vm, 'handleClose')
			const buttons = wrapper.vm.dialogButtons as DialogButton[]
			// First button is Cancel (not primary)
			const cancelButton = buttons.find((btn) => btn.type !== 'primary')
			expect(cancelButton).toBeDefined()
			cancelButton!.callback()
			expect(handleCloseSpy).toHaveBeenCalled()
		})

		it('has Save button that calls handleSave', () => {
			const handleSaveSpy = vi.spyOn(wrapper.vm, 'handleSave')
			const buttons = wrapper.vm.dialogButtons as DialogButton[]
			const saveButton = buttons.find((btn) => btn.type === 'primary')
			expect(saveButton).toBeDefined()
			saveButton!.callback()
			expect(handleSaveSpy).toHaveBeenCalled()
		})
	})

	describe('handleSave method', () => {
		it('does not emit when name is invalid', () => {
			wrapper.setData({ localName: '' })
			wrapper.vm.handleSave()
			expect(wrapper.emitted('close')).toBeUndefined()
		})

		it('emits close with trimmed name when valid', () => {
			wrapper.setData({ localName: '  Valid Name  ' })
			wrapper.vm.handleSave()
			expect(wrapper.emitted('close')).toBeTruthy()
			expect(wrapper.emitted('close')?.[0]).toEqual(['Valid Name'])
		})

		it('does not emit when trimmed name is empty (handled by isNameValid)', () => {
			wrapper.setData({ localName: '   ' })
			wrapper.vm.handleSave()
			// isNameValid already catches this case, so handleSave returns early
			expect(wrapper.emitted('close')).toBeUndefined()
		})

		it('does not emit when name is empty (handled by isNameValid)', () => {
			wrapper.setData({ localName: '' })
			wrapper.vm.handleSave()
			// isNameValid already catches this case, so handleSave returns early
			expect(wrapper.emitted('close')).toBeUndefined()
		})

		it('emits with single character name (minimum valid)', () => {
			wrapper.setData({ localName: 'A' })
			wrapper.vm.handleSave()
			expect(wrapper.emitted('close')).toBeTruthy()
			expect(wrapper.emitted('close')?.[0]).toEqual(['A'])
		})

		it('emits with max length name', () => {
			const maxName = 'A'.repeat(ENVELOPE_NAME_MAX_LENGTH)
			wrapper.setData({ localName: maxName })
			wrapper.vm.handleSave()
			expect(wrapper.emitted('close')).toBeTruthy()
			expect(wrapper.emitted('close')?.[0]).toEqual([maxName])
		})

		it('preserves internal spaces in name', () => {
			wrapper.setData({ localName: '  Name With   Spaces  ' })
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
		it('sets success message and clears error message', () => {
			wrapper.setData({ localErrorMessage: 'Previous error' })
			wrapper.vm.showSuccess('Success!')
			expect(wrapper.vm.localSuccessMessage).toBe('Success!')
			expect(wrapper.vm.localErrorMessage).toBe('')
		})

		it('clears previous success message', () => {
			wrapper.setData({ localSuccessMessage: 'Old success' })
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
		it('sets error message and clears success message', () => {
			wrapper.setData({ localSuccessMessage: 'Previous success' })
			wrapper.vm.showError('Error!')
			expect(wrapper.vm.localErrorMessage).toBe('Error!')
			expect(wrapper.vm.localSuccessMessage).toBe('')
		})

		it('clears previous error message', () => {
			wrapper.setData({ localErrorMessage: 'Old error' })
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
		it('clears both success and error messages', () => {
			wrapper.setData({
				localSuccessMessage: 'Success',
				localErrorMessage: 'Error',
			})
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
		it('handles complete edit flow: open with name, edit, and save', () => {
			wrapper = shallowMount(EditNameDialog, {
				props: { name: 'Original Name' },
				stubs: {
					NcButton: true,
					NcDialog: true,
					NcNoteCard: true,
					NcTextField: true,
				},
			})

			expect(wrapper.vm.localName).toBe('Original Name')

			wrapper.setData({ localName: 'Edited Name' })
			wrapper.vm.handleSave()

			expect(wrapper.emitted('close')).toBeTruthy()
			expect(wrapper.emitted('close')?.[0]).toEqual(['Edited Name'])
		})

		it('handles cancel flow without changes', () => {
			wrapper = shallowMount(EditNameDialog, {
				props: { name: 'Original Name' },
				stubs: {
					NcButton: true,
					NcDialog: true,
					NcNoteCard: true,
					NcTextField: true,
				},
			})

			wrapper.vm.handleClose()

			expect(wrapper.emitted('close')).toBeTruthy()
			expect(wrapper.emitted('close')?.[0]).toEqual([null])
		})

		it('handles edge case: exactly at boundary lengths', () => {
			const minName = 'A'.repeat(ENVELOPE_NAME_MIN_LENGTH)
			wrapper.setData({ localName: minName })
			expect(wrapper.vm.isNameValid).toBe(true)

			const maxName = 'B'.repeat(ENVELOPE_NAME_MAX_LENGTH)
			wrapper.setData({ localName: maxName })
			expect(wrapper.vm.isNameValid).toBe(true)

			const tooLong = 'C'.repeat(ENVELOPE_NAME_MAX_LENGTH + 1)
			wrapper.setData({ localName: tooLong })
			expect(wrapper.vm.isNameValid).toBe(false)
		})
	})
})
