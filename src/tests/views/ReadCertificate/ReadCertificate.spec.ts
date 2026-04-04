/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import axios from '@nextcloud/axios'

import ReadCertificate from '../../../views/ReadCertificate/ReadCertificate.vue'

const signMethodsStoreMock = {
	modal: {
		readCertificate: true,
	},
	closeModal: vi.fn(),
}

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

vi.mock('../../../store/signMethods.js', () => ({
	useSignMethodsStore: vi.fn(() => signMethodsStoreMock),
}))

describe('ReadCertificate.vue', () => {
	beforeEach(() => {
		signMethodsStoreMock.modal.readCertificate = true
		signMethodsStoreMock.closeModal.mockReset()
		vi.mocked(axios.post).mockReset()
	})

	function createWrapper() {
		return mount(ReadCertificate, {
			global: {
				stubs: {
					NcDialog: {
						name: 'NcDialog',
						props: ['name', 'size', 'isForm'],
						emits: ['submit', 'closing'],
						template: '<div class="dialog-stub"><slot /><slot name="actions" /></div>',
					},
					NcPasswordField: {
						name: 'NcPasswordField',
						props: ['modelValue', 'disabled', 'label', 'placeholder'],
						emits: ['update:modelValue'],
						template: '<input class="password-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
					},
					NcButton: {
						name: 'NcButton',
						props: ['disabled', 'type', 'variant'],
						emits: ['click'],
						template: '<button class="button-stub" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
					},
					NcNoteCard: {
						name: 'NcNoteCard',
						props: ['type'],
						template: '<div class="note-card"><slot /></div>',
					},
					NcLoadingIcon: {
						name: 'NcLoadingIcon',
						props: ['size'],
						template: '<span class="loading-stub"></span>',
					},
					CertificateContent: {
						name: 'CertificateContent',
						props: ['certificate'],
						template: '<div class="certificate-content-stub"></div>',
					},
				},
			},
		})
	}

	it('resets its local state on mount', () => {
		const wrapper = createWrapper()

		expect(wrapper.vm.password).toBe('')
		expect(wrapper.vm.certificate).toEqual({})
		expect(wrapper.vm.error).toBe('')
		expect(wrapper.vm.size).toBe('small')
	})

	it('stores certificate data and expands the dialog on successful read', async () => {
		vi.mocked(axios.post).mockResolvedValue({
			data: {
				ocs: {
					data: {
						subject: 'LibreSign Certificate',
					},
				},
			},
		})
		const wrapper = createWrapper()
		wrapper.vm.password = 'secret'

		await wrapper.vm.send()

		expect(wrapper.vm.certificate).toEqual({ subject: 'LibreSign Certificate' })
		expect(wrapper.vm.size).toBe('large')
		expect(wrapper.vm.error).toBe('')
		expect(wrapper.vm.hasLoading).toBe(false)
	})

	it('closes the modal and resets the state', async () => {
		const wrapper = createWrapper()
		wrapper.vm.password = 'secret'
		wrapper.vm.certificate = { subject: 'Existing' }
		wrapper.vm.size = 'large'

		wrapper.vm.onClose()

		expect(signMethodsStoreMock.closeModal).toHaveBeenCalledWith('readCertificate')
		expect(wrapper.vm.password).toBe('')
		expect(wrapper.vm.certificate).toEqual({})
		expect(wrapper.vm.size).toBe('small')
	})
})
