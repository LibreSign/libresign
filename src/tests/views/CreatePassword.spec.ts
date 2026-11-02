/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import axios from '@nextcloud/axios'

import CreatePassword from '../../views/CreatePassword.vue'

const signMethodsStore = {
	modal: {
		createPassword: true,
	},
	setHasSignatureFile: vi.fn(),
	closeModal: vi.fn(),
}

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(),
	},
}))

vi.mock('@nextcloud/dialogs', () => ({
	showSuccess: vi.fn(),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

vi.mock('../../store/signMethods.js', () => ({
	useSignMethodsStore: vi.fn(() => signMethodsStore),
}))

describe('CreatePassword.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		signMethodsStore.modal.createPassword = true
	})

	it('emits success and closes the modal after creating the password', async () => {
		vi.mocked(axios.post).mockResolvedValue({})
		const wrapper = shallowMount(CreatePassword, {
			global: {
				stubs: {
					NcDialog: true,
					NcNoteCard: true,
					NcPasswordField: true,
					NcButton: true,
					NcLoadingIcon: true,
				},
			},
		})

		wrapper.vm.password = 'secret-123'
		await wrapper.vm.send()

		expect(axios.post).toHaveBeenCalledWith('/apps/libresign/api/v1/account/signature', {
			signPassword: 'secret-123',
		})
		expect(signMethodsStore.setHasSignatureFile).toHaveBeenCalledWith(true)
		expect(signMethodsStore.closeModal).toHaveBeenCalledWith('createPassword')
		expect(wrapper.emitted('password:created')).toEqual([[true]])
		expect(wrapper.vm.password).toBe('')
		expect(wrapper.vm.hasLoading).toBe(false)
	})

	it('stores the error message and emits failure when the request fails', async () => {
		vi.mocked(axios.post).mockRejectedValue({
			response: {
				data: {
					ocs: {
						data: {
							message: 'Password rejected',
						},
					},
				},
			},
		})
		const wrapper = shallowMount(CreatePassword, {
			global: {
				stubs: {
					NcDialog: true,
					NcNoteCard: true,
					NcPasswordField: true,
					NcButton: true,
					NcLoadingIcon: true,
				},
			},
		})

		wrapper.vm.password = 'bad-password'
		await wrapper.vm.send()

		expect(signMethodsStore.setHasSignatureFile).toHaveBeenCalledWith(false)
		expect(wrapper.vm.errorMessage).toBe('Password rejected')
		expect(wrapper.emitted('password:created')).toEqual([[false]])
		expect(wrapper.vm.hasLoading).toBe(false)
	})
})
