/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import ResetPassword from '../../views/ResetPassword.vue'

const {
	closeModalMock,
	patchMock,
	showSuccessMock,
	showErrorMock,
} = vi.hoisted(() => ({
	closeModalMock: vi.fn(),
	patchMock: vi.fn(),
	showSuccessMock: vi.fn(),
	showErrorMock: vi.fn(),
}))

vi.mock('../../store/signMethods.js', () => ({
	useSignMethodsStore: () => ({
		modal: {
			resetPassword: true,
		},
		closeModal: closeModalMock,
	}),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		patch: patchMock,
	},
}))

vi.mock('@nextcloud/dialogs', () => ({
	showSuccess: showSuccessMock,
	showError: showErrorMock,
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	isRTL: vi.fn(() => false),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
}))

describe('ResetPassword.vue', () => {
	beforeEach(() => {
		closeModalMock.mockReset()
		patchMock.mockReset()
		showSuccessMock.mockReset()
		showErrorMock.mockReset()
	})

	it('clears sensitive fields when modal is closed', async () => {
		const wrapper = shallowMount(ResetPassword, {
			global: {
				stubs: {
					NcDialog: true,
					NcPasswordField: true,
					NcButton: true,
					NcLoadingIcon: true,
				},
			},
		})

		await wrapper.setData({
			currentPassword: 'current-secret',
			newPassword: 'new-secret',
			rPassword: 'new-secret',
			hasLoading: true,
		})

		wrapper.vm.onClose()

		expect(closeModalMock).toHaveBeenCalledWith('resetPassword')
		expect(wrapper.vm.currentPassword).toBe('')
		expect(wrapper.vm.newPassword).toBe('')
		expect(wrapper.vm.rPassword).toBe('')
		expect(wrapper.vm.hasLoading).toBe(false)
	})

	it('clears fields after successful confirmation', async () => {
		patchMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						message: 'Password changed',
					},
				},
			},
		})

		const wrapper = shallowMount(ResetPassword, {
			global: {
				stubs: {
					NcDialog: true,
					NcPasswordField: true,
					NcButton: true,
					NcLoadingIcon: true,
				},
			},
		})

		await wrapper.setData({
			currentPassword: 'current-secret',
			newPassword: 'new-secret',
			rPassword: 'new-secret',
		})

		await wrapper.vm.send()

		expect(patchMock).toHaveBeenCalledWith('/apps/libresign/api/v1/account/pfx', {
			current: 'current-secret',
			new: 'new-secret',
		})
		expect(showSuccessMock).toHaveBeenCalledWith('Password changed')
		expect(closeModalMock).toHaveBeenCalledWith('resetPassword')
		expect(wrapper.vm.currentPassword).toBe('')
		expect(wrapper.vm.newPassword).toBe('')
		expect(wrapper.vm.rPassword).toBe('')
		expect(wrapper.vm.hasLoading).toBe(false)
		expect(wrapper.emitted('close')).toEqual([[true]])
	})
})
