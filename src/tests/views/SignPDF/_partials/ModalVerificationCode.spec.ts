/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import ModalVerificationCode from '@/views/SignPDF/_partials/ModalVerificationCode.vue'
import { useSignMethodsStore } from '@/store/signMethods.js'

// Mock axios
vi.mock('@nextcloud/axios', () => ({
	default: vi.fn().mockResolvedValue({ data: { ocs: { data: {} } } }),
	post: vi.fn().mockResolvedValue({ data: { ocs: { data: { message: 'Code sent' } } } }),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => `/ocs/v2.php/apps/libresign${path}`),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn(() => 6),
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

vi.mock('@nextcloud/password-confirmation', () => ({
	confirmPassword: vi.fn().mockResolvedValue(true),
}))

describe('ModalVerificationCode (email mode)', () => {
	let wrapper: ReturnType<typeof mount>
	let signMethodsStore: ReturnType<typeof useSignMethodsStore>

	const stubs = {
		NcDialog: { template: '<div><slot /></div>' },
		NcTextField: { template: '<input />' },
		NcButton: { template: '<button><slot /></button>' },
		NcLoadingIcon: { template: '<div />' },
		NcIconSvgWrapper: { template: '<div />' },
	}

	const stubsWithActions = {
		...stubs,
		NcDialog: { template: '<div><slot name="actions" /></div>' },
	}

	const stubsWithName = {
		...stubs,
		NcDialog: { props: ['name'], template: '<div><slot :name="name" /></div>' },
	}

	const mountEmail = (extraProps = {}) => mount(ModalVerificationCode, {
		props: { mode: 'email', ...extraProps },
		global: { stubs },
	})

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
		wrapper = mountEmail()

		const progressIndicator = wrapper.find('.progress-indicator')
		expect(progressIndicator.exists()).toBe(true)
		expect(progressIndicator.text()).toContain('Step 1 of 3 - Email verification')
	})

	it('displays explanatory text on step 1', async () => {
		wrapper = mountEmail()

		const explanation = wrapper.find('.step-explanation')
		expect(explanation.exists()).toBe(true)
		expect(explanation.text()).toContain('verify your identity')
		expect(explanation.text()).toContain('verification code')
	})

	it('shows correct dialog title for step 1', async () => {
		wrapper = mount(ModalVerificationCode, {
			props: { mode: 'email' },
			global: { stubs: stubsWithName },
		})

		expect(wrapper.vm.dialogTitle).toBe('Email verification')
	})

	it('renders step-content class for styling', async () => {
		wrapper = mountEmail()

		expect(wrapper.find('.step-content').exists()).toBe(true)
	})

	it('shows contact on step 2', async () => {
		signMethodsStore.settings.emailToken.hasConfirmCode = true
		wrapper = mountEmail()

		const contactDisplay = wrapper.find('.contact-display')
		expect(contactDisplay.exists()).toBe(true)
		expect(contactDisplay.text()).toContain('u***@email.com')
	})

	it('shows correct state on step 1', async () => {
		wrapper = mount(ModalVerificationCode, {
			props: { mode: 'email' },
			global: { stubs: stubsWithActions },
		})

		expect(wrapper.vm.signMethodsStore.settings.emailToken.hasConfirmCode).toBe(false)
		expect(wrapper.vm.identityVerified).toBe(false)
	})

	it('shows correct state on step 2', async () => {
		signMethodsStore.settings.emailToken.hasConfirmCode = true
		wrapper = mount(ModalVerificationCode, {
			props: { mode: 'email' },
			global: { stubs: stubsWithActions },
		})

		expect(wrapper.vm.signMethodsStore.settings.emailToken.hasConfirmCode).toBe(true)
		expect(wrapper.vm.identityVerified).toBe(false)
	})

	it('updates to step 3 when identityVerified is true', async () => {
		signMethodsStore.settings.emailToken.hasConfirmCode = true
		wrapper = mountEmail()

		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		expect(wrapper.find('.progress-indicator').text()).toContain('Step 3 of 3 - Signature confirmation')
	})

	it('shows verification success message on step 3', async () => {
		signMethodsStore.settings.emailToken.hasConfirmCode = true
		wrapper = mountEmail()

		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		const verificationSuccess = wrapper.find('.verification-success')
		expect(verificationSuccess.exists()).toBe(true)
		expect(verificationSuccess.text()).toContain('Your identity has been verified')
		expect(verificationSuccess.text()).toContain('You can now sign the document')
	})

	it('shows correct dialog title on step 3', async () => {
		signMethodsStore.settings.emailToken.hasConfirmCode = true
		wrapper = mount(ModalVerificationCode, {
			props: { mode: 'email' },
			global: { stubs: stubsWithActions },
		})

		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.dialogTitle).toBe('Signature confirmation')
	})

	it('sendCode sets identityVerified to true', async () => {
		signMethodsStore.settings.emailToken.hasConfirmCode = true
		wrapper = mountEmail()

		wrapper.vm.token = '123456'
		wrapper.vm.sendCode()

		expect(wrapper.vm.identityVerified).toBe(true)
	})

	it('requestNewCode resets identityVerified', async () => {
		wrapper = mountEmail()

		wrapper.vm.identityVerified = true
		wrapper.vm.requestNewCode()

		expect(wrapper.vm.identityVerified).toBe(false)
	})
})

describe('ModalVerificationCode (token mode)', () => {
	let wrapper: ReturnType<typeof mount>
	let signMethodsStore: ReturnType<typeof useSignMethodsStore>

	const stubs = {
		NcDialog: { template: '<div><slot /></div>' },
		NcTextField: { template: '<input />' },
		NcButton: { template: '<button><slot /></button>' },
		NcLoadingIcon: { template: '<div />' },
		NcIconSvgWrapper: { template: '<div />' },
	}

	const stubsWithActions = {
		...stubs,
		NcDialog: { template: '<div><slot name="actions" /></div>' },
	}

	const stubsWithName = {
		...stubs,
		NcDialog: { props: ['name'], template: '<div><slot :name="name" /></div>' },
	}

	const mountToken = (extraProps = {}) => mount(ModalVerificationCode, {
		props: { mode: 'token', phoneNumber: '', ...extraProps },
		global: { stubs },
	})

	beforeEach(() => {
		setActivePinia(createPinia())
		signMethodsStore = useSignMethodsStore()
		signMethodsStore.modal.token = true
		signMethodsStore.settings.smsToken = {
			identifyMethod: 'email',
		}
	})

	it('displays progress indicator on step 1', async () => {
		wrapper = mountToken()

		const progressIndicator = wrapper.find('.progress-indicator')
		expect(progressIndicator.exists()).toBe(true)
		expect(progressIndicator.text()).toContain('Step 1 of 3 - Identity verification')
	})

	it('displays generic explanatory text (not phone-specific) on step 1', async () => {
		wrapper = mountToken()

		const explanation = wrapper.find('.step-explanation')
		expect(explanation.exists()).toBe(true)
		expect(explanation.text()).toContain('verify your identity')
		expect(explanation.text()).toContain('contact information')
		expect(explanation.text()).not.toContain('phone')
	})

	it('shows correct dialog title for step 1', async () => {
		wrapper = mount(ModalVerificationCode, {
			props: { mode: 'token', phoneNumber: '' },
			global: { stubs: stubsWithName },
		})

		expect(wrapper.vm.dialogTitle).toBe('Identity verification')
	})

	it('uses generic "Contact information" label, not phone-specific', async () => {
		wrapper = mount(ModalVerificationCode, {
			props: { mode: 'token', phoneNumber: '' },
			global: {
				stubs: {
					...stubs,
					NcTextField: { props: ['label'], template: '<input :label="label" />' },
				},
			},
		})

		expect(wrapper.html()).toContain('Contact information')
		expect(wrapper.html()).not.toContain('Phone number')
	})

	it('renders step-content class for styling', async () => {
		wrapper = mountToken()

		expect(wrapper.find('.step-content').exists()).toBe(true)
	})

	it('displays progress with correct 3-step numbering', async () => {
		wrapper = mountToken()

		expect(wrapper.vm.progressText).toContain('of 3')
		expect(wrapper.vm.progressText).not.toContain('of 2')
	})

	it('updates to step 2 when tokenRequested is true', async () => {
		wrapper = mountToken({ phoneNumber: '+5511999999999' })

		wrapper.vm.tokenRequested = true
		await wrapper.vm.$nextTick()

		expect(wrapper.find('.progress-indicator').text()).toContain('Step 2 of 3 - Code validation')
	})

	it('updates to step 3 when identityVerified is true', async () => {
		wrapper = mountToken({ phoneNumber: '+5511999999999' })

		wrapper.vm.tokenRequested = true
		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		expect(wrapper.find('.progress-indicator').text()).toContain('Step 3 of 3 - Signature confirmation')
	})

	it('shows correct state on step 1', async () => {
		wrapper = mount(ModalVerificationCode, {
			props: { mode: 'token', phoneNumber: '' },
			global: { stubs: stubsWithActions },
		})

		expect(wrapper.vm.tokenRequested).toBe(false)
		expect(wrapper.vm.identityVerified).toBe(false)
	})

	it('shows correct state on step 2', async () => {
		wrapper = mount(ModalVerificationCode, {
			props: { mode: 'token', phoneNumber: '+5511999999999' },
			global: { stubs: stubsWithActions },
		})

		wrapper.vm.tokenRequested = true
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.tokenRequested).toBe(true)
		expect(wrapper.vm.identityVerified).toBe(false)
	})

	it('shows verification success message on step 3', async () => {
		wrapper = mountToken({ phoneNumber: '+5511999999999' })

		wrapper.vm.tokenRequested = true
		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		const verificationSuccess = wrapper.find('.verification-success')
		expect(verificationSuccess.exists()).toBe(true)
		expect(verificationSuccess.text()).toContain('Your identity has been verified')
		expect(verificationSuccess.text()).toContain('You can now sign the document')
	})

	it('shows correct dialog title on step 3', async () => {
		wrapper = mount(ModalVerificationCode, {
			props: { mode: 'token', phoneNumber: '+5511999999999' },
			global: { stubs: stubsWithActions },
		})

		wrapper.vm.tokenRequested = true
		wrapper.vm.identityVerified = true
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.dialogTitle).toBe('Signature confirmation')
	})

	it('sendCode sets identityVerified to true', async () => {
		wrapper = mountToken({ phoneNumber: '+5511999999999' })

		wrapper.vm.tokenRequested = true
		wrapper.vm.token = '123456'
		wrapper.vm.sendCode()

		expect(wrapper.vm.identityVerified).toBe(true)
	})

	it('requestNewCode resets tokenRequested state', async () => {
		wrapper = mountToken({ phoneNumber: '+5511999999999' })

		wrapper.vm.tokenRequested = true
		wrapper.vm.token = '123456'
		wrapper.vm.identityVerified = true

		wrapper.vm.requestNewCode()

		expect(wrapper.vm.tokenRequested).toBe(false)
		expect(wrapper.vm.token).toBe('')
		expect(wrapper.vm.identityVerified).toBe(false)
	})
})
