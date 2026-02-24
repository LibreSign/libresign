/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import CreateAccount from '../../views/CreateAccount.vue'
// @ts-ignore: No types available for crypto-js/md5
import md5 from 'crypto-js/md5'

// Mock @nextcloud modules
vi.mock('@nextcloud/dialogs', () => ({
	showWarning: vi.fn(),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => {
		if (key === 'settings') {
			return {
				accountHash: md5('test@example.com').toString(),
			}
		}
		if (key === 'message') {
			return 'Test message'
		}
		return defaultValue
	}),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path) => path),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(),
	},
}))

// Mock router
const mockRoute = {
	params: { uuid: 'test-uuid' },
}

const mockRouter = {
	resolve: vi.fn(() => ({ href: '/apps/libresign/sign' })),
}

describe('CreateAccount.vue - Business Logic', () => {
	let wrapper!: ReturnType<typeof shallowMount>

	beforeEach(() => {
		wrapper = shallowMount(CreateAccount, {
			mocks: {
				$route: mockRoute,
				$router: mockRouter,
			},
			stubs: {
				NcNoteCard: true,
				NcTextField: true,
				NcPasswordField: true,
				NcButton: true,
				NcLoadingIcon: true,
				EmailIcon: true,
				RightIcon: true,
			},
		})
	})

	describe('emailError computed property', () => {
		it('returns empty string when email is not filled', () => {
			wrapper.setData({ email: '' })
			expect(wrapper.vm.emailError).toBe('')
		})

		it('returns error message for invalid email format', async () => {
			wrapper.setData({ email: 'invalid-email' })
			await wrapper.vm.v$.email.$touch()
			expect(wrapper.vm.emailError).toBe('This is not a valid email')
		})

		it('returns error when email does not match invitation', async () => {
			wrapper.setData({ email: 'wrong@example.com' })
			await wrapper.vm.$nextTick()
			// Force vuelidate to not show format error
			wrapper.vm.v$.email.$model = 'wrong@example.com'
			await wrapper.vm.$nextTick()
			expect(wrapper.vm.emailError).toBe('The email entered is not the same as the email in the invitation')
		})

		it('returns empty string for valid matching email', async () => {
			wrapper.setData({ email: 'test@example.com' })
			await wrapper.vm.$nextTick()
			expect(wrapper.vm.emailError).toBe('')
		})
	})

	describe('showErrorEmail computed property', () => {
		it('returns true when emailError has content', async () => {
			wrapper.setData({ email: 'invalid' })
			await wrapper.vm.v$.email.$touch()
			expect(wrapper.vm.showErrorEmail).toBe(true)
		})

		it('returns false when emailError is empty', () => {
			wrapper.setData({ email: '' })
			expect(wrapper.vm.showErrorEmail).toBe(false)
		})

		it('returns false for very short error messages', () => {
			// This tests the length > 2 condition
			wrapper.setData({ email: '' })
			expect(wrapper.vm.emailError.length).toBeLessThanOrEqual(2)
			expect(wrapper.vm.showErrorEmail).toBe(false)
		})
	})

	describe('passwordError computed property', () => {
		it('returns empty string when password is not filled', () => {
			wrapper.setData({ password: '' })
			expect(wrapper.vm.passwordError).toBe('')
		})

		it('returns empty string when passwordConfirm is not filled', () => {
			wrapper.setData({ password: 'test', passwordConfirm: '' })
			expect(wrapper.vm.passwordError).toBe('')
		})

		it('returns error for password with 4 or less characters', () => {
			wrapper.setData({ password: '1234', passwordConfirm: '1234' })
			expect(wrapper.vm.passwordError).toBe('Your password must be greater than 4 digits')
		})

		it('returns error for password with exactly 4 characters', () => {
			wrapper.setData({ password: 'abcd', passwordConfirm: 'abcd' })
			expect(wrapper.vm.passwordError).toBe('Your password must be greater than 4 digits')
		})

		it('returns empty string for password with more than 4 characters', () => {
			wrapper.setData({ password: '12345', passwordConfirm: '12345' })
			expect(wrapper.vm.passwordError).toBe('')
		})

		it('returns empty string for password with 5 characters', () => {
			wrapper.setData({ password: 'abcde', passwordConfirm: 'abcde' })
			expect(wrapper.vm.passwordError).toBe('')
		})
	})

	describe('confirmPasswordError computed property', () => {
		it('returns empty string when password is not filled', () => {
			wrapper.setData({ password: '', passwordConfirm: 'test' })
			expect(wrapper.vm.confirmPasswordError).toBe('')
		})

		it('returns empty string when passwordConfirm is not filled', () => {
			wrapper.setData({ password: 'test', passwordConfirm: '' })
			expect(wrapper.vm.confirmPasswordError).toBe('')
		})

		it('returns error when passwords do not match', () => {
			wrapper.setData({ password: 'password1', passwordConfirm: 'password2' })
			expect(wrapper.vm.confirmPasswordError).toBe('Passwords does not match')
		})

		it('returns empty string when passwords match', () => {
			wrapper.setData({ password: 'password123', passwordConfirm: 'password123' })
			expect(wrapper.vm.confirmPasswordError).toBe('')
		})

		it('returns error for case-sensitive mismatch', () => {
			wrapper.setData({ password: 'Password', passwordConfirm: 'password' })
			expect(wrapper.vm.confirmPasswordError).toBe('Passwords does not match')
		})
	})

	describe('isEqualEmail computed property', () => {
		it('returns true when email matches the account hash', () => {
			wrapper.setData({ email: 'test@example.com' })
			expect(wrapper.vm.isEqualEmail).toBe(true)
		})

		it('returns false when email does not match the account hash', () => {
			wrapper.setData({ email: 'wrong@example.com' })
			expect(wrapper.vm.isEqualEmail).toBe(false)
		})

		it('is case-sensitive', () => {
			wrapper.setData({ email: 'Test@example.com' })
			expect(wrapper.vm.isEqualEmail).toBe(false)
		})

		it('returns false for empty email', () => {
			wrapper.setData({ email: '' })
			expect(wrapper.vm.isEqualEmail).toBe(false)
		})
	})

	describe('canSave computed property', () => {
		it('returns false when password is empty', () => {
			wrapper.setData({
				email: 'test@example.com',
				password: '',
				passwordConfirm: 'test',
			})
			expect(wrapper.vm.canSave).toBe(false)
		})

		it('returns false when passwordConfirm is empty', () => {
			wrapper.setData({
				email: 'test@example.com',
				password: 'test123',
				passwordConfirm: '',
			})
			expect(wrapper.vm.canSave).toBe(false)
		})

		it('returns false when email is empty', () => {
			wrapper.setData({
				email: '',
				password: 'test123',
				passwordConfirm: 'test123',
			})
			expect(wrapper.vm.canSave).toBe(false)
		})

		it('returns false when password has error', () => {
			wrapper.setData({
				email: 'test@example.com',
				password: '123',
				passwordConfirm: '123',
			})
			expect(wrapper.vm.canSave).toBe(false)
		})

		it('returns false when passwords do not match', () => {
			wrapper.setData({
				email: 'test@example.com',
				password: 'password1',
				passwordConfirm: 'password2',
			})
			expect(wrapper.vm.canSave).toBe(false)
		})

		it('returns false when email has error', async () => {
			wrapper.setData({
				email: 'invalid-email',
				password: 'password123',
				passwordConfirm: 'password123',
			})
			await wrapper.vm.v$.email.$touch()
			expect(wrapper.vm.canSave).toBe(false)
		})

		it('returns false when loading', () => {
			wrapper.setData({
				email: 'test@example.com',
				password: 'password123',
				passwordConfirm: 'password123',
				loading: true,
			})
			expect(wrapper.vm.canSave).toBe(false)
		})

		it('returns true when all validations pass', () => {
			wrapper.setData({
				email: 'test@example.com',
				password: 'password123',
				passwordConfirm: 'password123',
				loading: false,
			})
			expect(wrapper.vm.canSave).toBe(true)
		})

		it('returns false when email does not match invitation', () => {
			wrapper.setData({
				email: 'wrong@example.com',
				password: 'password123',
				passwordConfirm: 'password123',
				loading: false,
			})
			expect(wrapper.vm.canSave).toBe(false)
		})
	})

	describe('Vuelidate validations', () => {
		it('validates email as required', async () => {
			wrapper.setData({ email: '' })
			await wrapper.vm.v$.email.$touch()
				expect(wrapper.vm.v$.email.required.$invalid).toBe(true)
		})

		it('validates email format', async () => {
			wrapper.setData({ email: 'not-an-email' })
			await wrapper.vm.v$.email.$touch()
				expect(wrapper.vm.v$.email.email.$invalid).toBe(true)
		})

		it('accepts valid email format', async () => {
			wrapper.setData({ email: 'test@example.com' })
			await wrapper.vm.v$.email.$touch()
				expect(wrapper.vm.v$.email.email.$invalid).toBe(false)
		})

		it('validates password as required', async () => {
			wrapper.setData({ password: '' })
			await wrapper.vm.v$.password.$touch()
				expect(wrapper.vm.v$.password.required.$invalid).toBe(true)
		})

		it('validates password minimum length', async () => {
			wrapper.setData({ password: '123' })
			await wrapper.vm.v$.password.$touch()
				expect(wrapper.vm.v$.password.minLength.$invalid).toBe(true)
		})

		it('accepts password with minimum length', async () => {
			wrapper.setData({ password: '1234' })
			await wrapper.vm.v$.password.$touch()
				expect(wrapper.vm.v$.password.minLength.$invalid).toBe(false)
		})

		it('validates passwordConfirm as required', async () => {
			wrapper.setData({ passwordConfirm: '' })
			await wrapper.vm.v$.passwordConfirm.$touch()
				expect(wrapper.vm.v$.passwordConfirm.required.$invalid).toBe(true)
		})

		it('validates passwordConfirm minimum length', async () => {
			wrapper.setData({ passwordConfirm: '123' })
			await wrapper.vm.v$.passwordConfirm.$touch()
				expect(wrapper.vm.v$.passwordConfirm.minLength.$invalid).toBe(true)
		})
	})

	describe('integration scenarios', () => {
		it('validates complete valid form', () => {
			wrapper.setData({
				email: 'test@example.com',
				password: 'validPassword123',
				passwordConfirm: 'validPassword123',
				loading: false,
			})

			expect(wrapper.vm.emailError).toBe('')
			expect(wrapper.vm.passwordError).toBe('')
			expect(wrapper.vm.confirmPasswordError).toBe('')
			expect(wrapper.vm.isEqualEmail).toBe(true)
			expect(wrapper.vm.canSave).toBe(true)
		})

		it('identifies form with email mismatch', () => {
			wrapper.setData({
				email: 'different@example.com',
				password: 'validPassword123',
				passwordConfirm: 'validPassword123',
				loading: false,
			})

			expect(wrapper.vm.isEqualEmail).toBe(false)
			expect(wrapper.vm.canSave).toBe(false)
		})

		it('identifies form with password mismatch', () => {
			wrapper.setData({
				email: 'test@example.com',
				password: 'password123',
				passwordConfirm: 'password456',
				loading: false,
			})

			expect(wrapper.vm.confirmPasswordError).toBeTruthy()
			expect(wrapper.vm.canSave).toBe(false)
		})

		it('identifies form with short password', () => {
			wrapper.setData({
				email: 'test@example.com',
				password: '123',
				passwordConfirm: '123',
				loading: false,
			})

			expect(wrapper.vm.passwordError).toBeTruthy()
			expect(wrapper.vm.canSave).toBe(false)
		})

		it('handles boundary case: exactly 4 character password', () => {
			wrapper.setData({
				email: 'test@example.com',
				password: '1234',
				passwordConfirm: '1234',
				loading: false,
			})

			// Password must be GREATER than 4, so 4 chars is invalid
			expect(wrapper.vm.passwordError).toBeTruthy()
			expect(wrapper.vm.canSave).toBe(false)
		})

		it('handles boundary case: exactly 5 character password', () => {
			wrapper.setData({
				email: 'test@example.com',
				password: '12345',
				passwordConfirm: '12345',
				loading: false,
			})

			expect(wrapper.vm.passwordError).toBe('')
			expect(wrapper.vm.canSave).toBe(true)
		})

		it('prevents submission when loading', () => {
			wrapper.setData({
				email: 'test@example.com',
				password: 'validPassword123',
				passwordConfirm: 'validPassword123',
				loading: true,
			})

			expect(wrapper.vm.canSave).toBe(false)
		})
	})
})
