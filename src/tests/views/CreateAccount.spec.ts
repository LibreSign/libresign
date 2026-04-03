/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import CreateAccount from '../../views/CreateAccount.vue'
import md5 from 'blueimp-md5'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

type ValidationField = {
	$model: string
	$touch: () => Promise<void>
	$error: boolean
	required: { $invalid: boolean }
	email: { $invalid: boolean }
	minLength: { $invalid: boolean }
}

type CreateAccountVm = InstanceType<typeof CreateAccount> & {
	v$: {
		email: ValidationField
		password: ValidationField
		passwordConfirm: ValidationField
	}
	emailError: string
	showErrorEmail: boolean
	passwordError: string
	confirmPasswordError: string
	isEqualEmail: boolean
	canSave: boolean
	loading: boolean
	email: string
	password: string
	passwordConfirm: string
	$nextTick: () => Promise<void>
}

// Mock @nextcloud modules
vi.mock('@nextcloud/dialogs', () => ({
	showWarning: vi.fn(),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => {
		if (key === 'settings') {
			return {
				accountHash: md5('test@example.com'),
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
	type CreateAccountWrapper = VueWrapper<any> & {
		vm: CreateAccountVm
		setData: (values: Record<string, unknown>) => Promise<void>
	}

	let wrapper!: CreateAccountWrapper
	const axiosPostMock = vi.mocked(axios.post)
	const generateOcsUrlMock = vi.mocked(generateOcsUrl)

	beforeEach(() => {
		axiosPostMock.mockReset()
		generateOcsUrlMock.mockClear()
		wrapper = shallowMount(CreateAccount, {
			global: {
				mocks: {
					$route: mockRoute,
					$router: mockRouter,
				},
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
		}) as CreateAccountWrapper

		wrapper.setData = async (values) => {
			const vm = wrapper.vm as unknown as CreateAccountVm & Record<string, unknown>
			for (const [key, value] of Object.entries(values)) {
				;(vm as Record<string, unknown>)[key] = value
				if (key in vm.v$ && typeof value === 'string') {
					vm.v$[key as keyof CreateAccountVm['v$']].$model = value
				}
			}
			await vm.$nextTick()
		}
	})

	describe('emailError computed property', () => {
		it('returns empty string when email is not filled', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ email: '' })
			expect(vm.emailError).toBe('')
		})

		it('returns error message for invalid email format', async () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ email: 'invalid-email' })
			await vm.v$.email.$touch()
			expect(vm.emailError).toBe('This is not a valid email')
		})

		it('returns error when email does not match invitation', async () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ email: 'wrong@example.com' })
			await wrapper.vm.$nextTick()
			expect(vm.emailError).toBe('The email entered is not the same as the email in the invitation')
		})

		it('returns empty string for valid matching email', async () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ email: 'test@example.com' })
			await wrapper.vm.$nextTick()
			expect(vm.emailError).toBe('')
		})
	})

	describe('showErrorEmail computed property', () => {
		it('returns true when emailError has content', async () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ email: 'invalid' })
			await vm.v$.email.$touch()
			expect(vm.showErrorEmail).toBe(true)
		})

		it('returns false when emailError is empty', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ email: '' })
			expect(vm.showErrorEmail).toBe(false)
		})

		it('returns false for very short error messages', () => {
			const vm = wrapper.vm as CreateAccountVm
			// This tests the length > 2 condition
			wrapper.setData({ email: '' })
			expect(vm.emailError.length).toBeLessThanOrEqual(2)
			expect(vm.showErrorEmail).toBe(false)
		})
	})

	describe('passwordError computed property', () => {
		it('returns empty string when password is not filled', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ password: '' })
			expect(vm.passwordError).toBe('')
		})

		it('returns empty string when passwordConfirm is not filled', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ password: 'test', passwordConfirm: '' })
			expect(vm.passwordError).toBe('')
		})

		it('returns error for password with 4 or less characters', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ password: '1234', passwordConfirm: '1234' })
			expect(vm.passwordError).toBe('Your password must be greater than 4 digits')
		})

		it('returns error for password with exactly 4 characters', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ password: 'abcd', passwordConfirm: 'abcd' })
			expect(vm.passwordError).toBe('Your password must be greater than 4 digits')
		})

		it('returns empty string for password with more than 4 characters', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ password: '12345', passwordConfirm: '12345' })
			expect(vm.passwordError).toBe('')
		})

		it('returns empty string for password with 5 characters', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ password: 'abcde', passwordConfirm: 'abcde' })
			expect(vm.passwordError).toBe('')
		})
	})

	describe('confirmPasswordError computed property', () => {
		it('returns empty string when password is not filled', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ password: '', passwordConfirm: 'test' })
			expect(vm.confirmPasswordError).toBe('')
		})

		it('returns empty string when passwordConfirm is not filled', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ password: 'test', passwordConfirm: '' })
			expect(vm.confirmPasswordError).toBe('')
		})

		it('returns error when passwords do not match', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ password: 'password1', passwordConfirm: 'password2' })
			expect(vm.confirmPasswordError).toBe('Passwords does not match')
		})

		it('returns empty string when passwords match', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ password: 'password123', passwordConfirm: 'password123' })
			expect(vm.confirmPasswordError).toBe('')
		})

		it('returns error for case-sensitive mismatch', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ password: 'Password', passwordConfirm: 'password' })
			expect(vm.confirmPasswordError).toBe('Passwords does not match')
		})
	})

	describe('isEqualEmail computed property', () => {
		it('returns true when email matches the account hash', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ email: 'test@example.com' })
			expect(vm.isEqualEmail).toBe(true)
		})

		it('returns false when email does not match the account hash', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ email: 'wrong@example.com' })
			expect(vm.isEqualEmail).toBe(false)
		})

		it('is case-sensitive', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ email: 'Test@example.com' })
			expect(vm.isEqualEmail).toBe(false)
		})

		it('returns false for empty email', () => {
			const vm = wrapper.vm as CreateAccountVm
			wrapper.setData({ email: '' })
			expect(vm.isEqualEmail).toBe(false)
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

		it('posts create-account request with interpolated sign request uuid', async () => {
			axiosPostMock.mockRejectedValue({
				response: {
					data: {
						ocs: {
							data: {
								message: 'Invalid UUID',
							},
						},
					},
				},
			})

			await wrapper.setData({
				email: 'test@example.com',
				password: 'validPassword123',
				passwordConfirm: 'validPassword123',
			})

			await wrapper.vm.createAccount()

			expect(generateOcsUrlMock).toHaveBeenCalledWith('/apps/libresign/api/v1/account/create/{uuid}', {
				uuid: 'test-uuid',
			})
			expect(axiosPostMock).toHaveBeenCalledWith('/apps/libresign/api/v1/account/create/{uuid}', {
				email: 'test@example.com',
				password: 'validPassword123',
			})
		})
	})
})
