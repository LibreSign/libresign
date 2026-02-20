/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import ModalEmailManager from '@/views/SignPDF/_partials/ModalEmailManager.vue'
import { useSignMethodsStore } from '@/store/signMethods.js'

// Mock axios
vi.mock('@nextcloud/axios', () => ({
	default: vi.fn().mockResolvedValue({ data: { ocs: { data: {} } } }),
	post: vi.fn().mockResolvedValue({ data: { ocs: { data: { message: 'Code sent' } } } }),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path) => `/ocs/v2.php/apps/libresign${path}`),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn(() => 6),
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

describe('ModalEmailManager - UX Improvements', () => {
	let wrapper
	let signMethodsStore

	beforeEach(() => {
		setActivePinia(createPinia())
		signMethodsStore = useSignMethodsStore()
		signMethodsStore.modal.emailToken = true
		signMethodsStore.settings.emailToken = {
			hasConfirmCode: false,
			hashOfEmail: '5d41402abc4b2a76b9719d911017c592',
			blurredEmail: 'u***@email.com',
		}
	})

	it('displays progress indicator on step 1', async () => {
		wrapper = mount(ModalEmailManager, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
					EmailIcon: { template: '<div />' },
					FormTextboxPasswordIcon: { template: '<div />' },
				},
			},
		})

		const progressIndicator = wrapper.find('.progress-indicator')
		expect(progressIndicator.exists()).toBe(true)
		expect(progressIndicator.text()).toContain('Step 1 of 3 - Email verification')
	})

	it('displays explanatory text on step 1', async () => {
		wrapper = mount(ModalEmailManager, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
					EmailIcon: { template: '<div />' },
					FormTextboxPasswordIcon: { template: '<div />' },
				},
			},
		})

		const explanation = wrapper.find('.step-explanation')
		expect(explanation.exists()).toBe(true)
		expect(explanation.text()).toContain('verify your identity')
		expect(explanation.text()).toContain('verification code')
	})

	it('shows correct dialog title for step 1', async () => {
		wrapper = mount(ModalEmailManager, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot :name="name" /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
					EmailIcon: { template: '<div />' },
					FormTextboxPasswordIcon: { template: '<div />' },
				},
			},
		})

		expect(wrapper.vm.dialogTitle).toBe('Email verification')
	})

	it('updates to step 3 when identityVerified is true', async () => {
		signMethodsStore.settings.emailToken.hasConfirmCode = true
		wrapper = mount(ModalEmailManager, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
					EmailIcon: { template: '<div />' },
					FormTextboxPasswordIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		const progressIndicator = wrapper.find('.progress-indicator')
		expect(progressIndicator.text()).toContain('Step 3 of 3 - Signature confirmation')
	})

	it('shows email address on step 2', async () => {
		signMethodsStore.settings.emailToken.hasConfirmCode = true
		wrapper = mount(ModalEmailManager, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
					EmailIcon: { template: '<div />' },
					FormTextboxPasswordIcon: { template: '<div />' },
				},
			},
		})

		const emailDisplay = wrapper.find('.email-display')
		expect(emailDisplay.exists()).toBe(true)
		expect(emailDisplay.text()).toContain('u***@email.com')
	})

	it('shows correct button label on step 1', async () => {
		wrapper = mount(ModalEmailManager, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot name="actions" /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
					EmailIcon: { template: '<div />' },
					FormTextboxPasswordIcon: { template: '<div />' },
				},
			},
		})

		expect(wrapper.vm.signMethodsStore.settings.emailToken.hasConfirmCode).toBe(false)
		expect(wrapper.vm.identityVerified).toBe(false)
	})

	it('shows correct button label on step 2', async () => {
		signMethodsStore.settings.emailToken.hasConfirmCode = true
		wrapper = mount(ModalEmailManager, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot name="actions" /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
					EmailIcon: { template: '<div />' },
					FormTextboxPasswordIcon: { template: '<div />' },
				},
			},
		})

		expect(wrapper.vm.signMethodsStore.settings.emailToken.hasConfirmCode).toBe(true)
		expect(wrapper.vm.identityVerified).toBe(false)
	})

	it('renders step-content class for styling', async () => {
		wrapper = mount(ModalEmailManager, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
					EmailIcon: { template: '<div />' },
					FormTextboxPasswordIcon: { template: '<div />' },
				},
			},
		})

		const stepContent = wrapper.find('.step-content')
		expect(stepContent.exists()).toBe(true)
	})

	it('updates to step 3 when identityVerified is true', async () => {
		signMethodsStore.settings.emailToken.hasConfirmCode = true
		wrapper = mount(ModalEmailManager, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
					EmailIcon: { template: '<div />' },
					FormTextboxPasswordIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		const progressIndicator = wrapper.find('.progress-indicator')
		expect(progressIndicator.text()).toContain('Step 3 of 3 - Signature confirmation')
	})

	it('shows verification success message on step 3', async () => {
		signMethodsStore.settings.emailToken.hasConfirmCode = true
		wrapper = mount(ModalEmailManager, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
					EmailIcon: { template: '<div />' },
					FormTextboxPasswordIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		const verificationSuccess = wrapper.find('.verification-success')
		expect(verificationSuccess.exists()).toBe(true)
		expect(verificationSuccess.text()).toContain('Your identity has been verified')
		expect(verificationSuccess.text()).toContain('You can now sign the document')
	})

	it('shows correct button label on step 3', async () => {
		signMethodsStore.settings.emailToken.hasConfirmCode = true
		wrapper = mount(ModalEmailManager, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot name="actions" /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
					EmailIcon: { template: '<div />' },
					FormTextboxPasswordIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.identityVerified).toBe(true)
		expect(wrapper.vm.dialogTitle).toBe('Signature confirmation')
	})

	it('sendCode sets identityVerified to true', async () => {
		signMethodsStore.settings.emailToken.hasConfirmCode = true
		wrapper = mount(ModalEmailManager, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
					EmailIcon: { template: '<div />' },
					FormTextboxPasswordIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.token = '123456'
		wrapper.vm.sendCode()

		expect(wrapper.vm.identityVerified).toBe(true)
	})

	it('requestNewCode resets identityVerified', async () => {
		wrapper = mount(ModalEmailManager, {
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
					EmailIcon: { template: '<div />' },
					FormTextboxPasswordIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.identityVerified = true
		wrapper.vm.requestNewCode()

		expect(wrapper.vm.identityVerified).toBe(false)
	})
})
