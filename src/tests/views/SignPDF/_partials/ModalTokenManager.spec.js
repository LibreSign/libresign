/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import ModalTokenManager from '@/views/SignPDF/_partials/ModalTokenManager.vue'
import { useSignMethodsStore } from '@/store/signMethods.js'

// Mock axios
vi.mock('@nextcloud/axios', () => ({
	default: vi.fn().mockResolvedValue({ data: { ocs: { data: {} } } }),
	post: vi.fn().mockResolvedValue({ data: { ocs: { data: { message: 'Code sent' } } } }),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path) => `/ocs/v2.php/apps/libresign${path}`),
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

vi.mock('@nextcloud/password-confirmation', () => ({
	confirmPassword: vi.fn().mockResolvedValue(true),
}))

describe('ModalTokenManager - UX Improvements', () => {
	let wrapper
	let signMethodsStore

	beforeEach(() => {
		setActivePinia(createPinia())
		signMethodsStore = useSignMethodsStore()
		signMethodsStore.modal.token = true
		signMethodsStore.settings.smsToken = {
			identifyMethod: 'email',
		}
	})

	it('displays progress indicator on step 1', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		const progressIndicator = wrapper.find('.progress-indicator')
		expect(progressIndicator.exists()).toBe(true)
		expect(progressIndicator.text()).toContain('Step 1 of 3 - Identity verification')
	})

	it('displays generic explanatory text (not phone-specific) on step 1', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		const explanation = wrapper.find('.step-explanation')
		expect(explanation.exists()).toBe(true)
		expect(explanation.text()).toContain('verify your identity')
		expect(explanation.text()).toContain('contact information')
		expect(explanation.text()).not.toContain('phone') // Should be generic
	})

	it('shows correct dialog title for step 1', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot :name="name" /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		expect(wrapper.vm.dialogTitle).toBe('Identity verification')
	})

	it('uses generic "Contact information" label, not phone-specific', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input :label="label" />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		expect(wrapper.html()).toContain('Contact information')
		expect(wrapper.html()).not.toContain('Phone number')
	})

	it('updates to step 2 when tokenRequested is true', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '+5511999999999',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.tokenRequested = true
		await wrapper.vm.$nextTick()

		const progressIndicator = wrapper.find('.progress-indicator')
		expect(progressIndicator.text()).toContain('Step 2 of 3 - Code validation')
	})

	it('updates to step 3 when identityVerified is true', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '+5511999999999',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.tokenRequested = true
		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		const progressIndicator = wrapper.find('.progress-indicator')
		expect(progressIndicator.text()).toContain('Step 3 of 3 - Signature confirmation')
	})

	it('shows correct button label on step 1', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot name="actions" /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		expect(wrapper.vm.tokenRequested).toBe(false)
		expect(wrapper.vm.identityVerified).toBe(false)
	})

	it('shows correct button label on step 2', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '+5511999999999',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot name="actions" /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.tokenRequested = true
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.tokenRequested).toBe(true)
		expect(wrapper.vm.identityVerified).toBe(false)
	})

	it('renders step-content class for styling', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		const stepContent = wrapper.find('.step-content')
		expect(stepContent.exists()).toBe(true)
	})

	it('displays progress with correct numbering (2 steps, not 3)', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		expect(wrapper.vm.progressText).toContain('of 3')
		expect(wrapper.vm.progressText).not.toContain('of 2')
	})

	it('requestNewCode resets tokenRequested state', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '+5511999999999',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.tokenRequested = true
		wrapper.vm.token = '123456'
		wrapper.vm.identityVerified = true

		wrapper.vm.requestNewCode()

		expect(wrapper.vm.tokenRequested).toBe(false)
		expect(wrapper.vm.token).toBe('')
		expect(wrapper.vm.identityVerified).toBe(false)
	})

	it('updates to step 3 when identityVerified is true', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '+5511999999999',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.tokenRequested = true
		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		const progressIndicator = wrapper.find('.progress-indicator')
		expect(progressIndicator.text()).toContain('Step 3 of 3 - Signature confirmation')
	})

	it('shows verification success message on step 3', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '+5511999999999',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.tokenRequested = true
		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		const verificationSuccess = wrapper.find('.verification-success')
		expect(verificationSuccess.exists()).toBe(true)
		expect(verificationSuccess.text()).toContain('Your identity has been verified')
		expect(verificationSuccess.text()).toContain('You can now sign the document')
	})

	it('shows correct button label on step 3', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '+5511999999999',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot name="actions" /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.tokenRequested = true
		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.identityVerified).toBe(true)
		expect(wrapper.vm.dialogTitle).toBe('Signature confirmation')
	})

	it('sendCode sets identityVerified to true', async () => {
		wrapper = mount(ModalTokenManager, {
			props: {
				phoneNumber: '+5511999999999',
			},
			global: {
				stubs: {
					NcDialog: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcButton: { template: '<button><slot /></button>' },
					NcLoadingIcon: { template: '<div />' },
				},
			},
		})

		wrapper.vm.tokenRequested = true
		wrapper.vm.token = '123456'
		wrapper.vm.sendCode()

		expect(wrapper.vm.identityVerified).toBe(true)
	})
})
