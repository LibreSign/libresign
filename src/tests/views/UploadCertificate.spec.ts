/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import axios from '@nextcloud/axios'

import UploadCertificate from '../../views/UploadCertificate.vue'

const signMethodsStoreMock = {
	modal: {
		uploadCertificate: true,
	},
	closeModal: vi.fn(),
	setHasSignatureFile: vi.fn(),
}

const showSuccessMock = vi.fn()
const showErrorMock = vi.fn()

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(),
	},
}))

vi.mock('@nextcloud/dialogs', () => ({
	showSuccess: vi.fn((...args: unknown[]) => showSuccessMock(...args)),
	showError: vi.fn((...args: unknown[]) => showErrorMock(...args)),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

vi.mock('../../store/signMethods.js', () => ({
	useSignMethodsStore: vi.fn(() => signMethodsStoreMock),
}))

describe('UploadCertificate.vue', () => {
	beforeEach(() => {
		signMethodsStoreMock.modal.uploadCertificate = true
		signMethodsStoreMock.closeModal.mockReset()
		signMethodsStoreMock.setHasSignatureFile.mockReset()
		showSuccessMock.mockReset()
		showErrorMock.mockReset()
		vi.mocked(axios.post).mockReset()
	})

	function createWrapper() {
		return mount(UploadCertificate, {
			props: {
				errors: [],
			},
			global: {
				stubs: {
					NcDialog: {
						name: 'NcDialog',
						template: '<div class="dialog-stub"><slot /><slot name="actions" /></div>',
						props: ['name'],
						emits: ['closing'],
					},
					NcButton: {
						name: 'NcButton',
						template: '<button><slot /><slot name="icon" /></button>',
						props: ['variant'],
					},
					NcNoteCard: {
						name: 'NcNoteCard',
						template: '<div class="note-card"><slot /></div>',
						props: ['heading', 'type'],
					},
				},
			},
		})
	}

	it('shows the dialog when modal usage is enabled in the store', () => {
		const wrapper = createWrapper()

		expect(wrapper.find('.dialog-stub').exists()).toBe(true)
		expect(wrapper.vm.showModal).toBe(true)
	})

	it('closes the modal and clears local errors', async () => {
		const wrapper = createWrapper()
		wrapper.vm.localErrors = [{ title: 'Error', message: 'Invalid file' }]

		wrapper.vm.closeDialog()

		expect(wrapper.vm.localErrors).toEqual([])
		expect(signMethodsStoreMock.closeModal).toHaveBeenCalledWith('uploadCertificate')
	})

	it('uploads a certificate and emits the uploaded event', async () => {
		vi.mocked(axios.post).mockResolvedValue({
			data: {
				ocs: {
					data: {
						message: 'Uploaded successfully',
					},
				},
			},
		})
		const wrapper = createWrapper()
		const file = new File(['binary'], 'certificate.pfx', { type: 'application/x-pkcs12' })

		await wrapper.vm.doUpload(file)

		expect(axios.post).toHaveBeenCalledTimes(1)
		expect(signMethodsStoreMock.setHasSignatureFile).toHaveBeenCalledWith(true)
		expect(signMethodsStoreMock.closeModal).toHaveBeenCalledWith('uploadCertificate')
		expect(showSuccessMock).toHaveBeenCalledWith('Uploaded successfully')
		expect(wrapper.emitted('certificate:uploaded')).toEqual([[]])
	})
})
