/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../../testHelpers/l10n.js'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'

const loadStateMock = vi.fn()
const hasSignatureFileMock = vi.fn()
const initializeHasSignatureFileMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('../../../../store/signMethods.js', () => ({
	useSignMethodsStore: () => ({
		hasSignatureFile: () => hasSignatureFileMock(),
		setHasSignatureFile: vi.fn(),
		initializeHasSignatureFile: (...args: unknown[]) => initializeHasSignatureFileMock(...args),
		showModal: vi.fn(),
	}),
}))

vi.mock('@nextcloud/l10n', () => createL10nMock())

type ManagePasswordComponent = typeof import('../../../../views/Account/partials/ManagePassword.vue').default

type ManagePasswordVm = {
	$nextTick: () => Promise<void>
	uploadCertificate?: { triggerUpload: () => void }
	triggerUploadCertificate: () => void
	mdiCloudUpload: string
	mdiLockOpenCheck: string
	mdiDelete: string
	mdiCertificate: string
	mdiFileReplace: string
}

type ManagePasswordWrapper = VueWrapper<any> & {
	vm: ManagePasswordVm
}

let ManagePassword: ManagePasswordComponent

beforeAll(async () => {
	;({ default: ManagePassword } = await import('../../../../views/Account/partials/ManagePassword.vue'))
})

describe('ManagePassword', () => {
	beforeEach(() => {
		loadStateMock.mockReset()
		hasSignatureFileMock.mockReset()
		initializeHasSignatureFileMock.mockReset()
	})

	it('initializes signature file state from config without forcing remount behavior', () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'certificate_engine') return 'openssl'
			if (key === 'config') return { hasSignatureFile: true }
			return fallback
		})
		hasSignatureFileMock.mockReturnValue(true)

		mount(ManagePassword, {
			global: {
				stubs: {
					NcButton: { template: '<button><slot /><slot name="icon" /></button>' },
					NcIconSvgWrapper: { name: 'NcIconSvgWrapper', props: ['path'], template: '<i class="icon" :data-path="path" />' },
					CreatePassword: true,
					ReadCertificate: true,
					ResetPassword: true,
					UploadCertificate: true,
				},
			},
		})

		expect(initializeHasSignatureFileMock).toHaveBeenCalledWith(true)
	})

	it('registers icon wrapper and exposes mdi icon paths used in template', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'certificate_engine') return 'openssl'
			if (key === 'config') return { hasSignatureFile: true }
			return fallback
		})
		hasSignatureFileMock.mockReturnValue(true)

		const wrapper = mount(ManagePassword, {
			global: {
				stubs: {
					NcButton: { template: '<button><slot /><slot name="icon" /></button>' },
					NcIconSvgWrapper: { name: 'NcIconSvgWrapper', props: ['path'], template: '<i class="icon" :data-path="path" />' },
					CreatePassword: true,
					ReadCertificate: { name: 'ReadCertificate', template: '<div />' },
					ResetPassword: true,
					UploadCertificate: { name: 'UploadCertificate', template: '<div />' },
				},
			},
		}) as ManagePasswordWrapper

		await wrapper.vm.$nextTick()

		expect(wrapper.findAll('.icon')).toHaveLength(4)
		expect(wrapper.vm.mdiCloudUpload).toBeTruthy()
		expect(wrapper.vm.mdiLockOpenCheck).toBeTruthy()
		expect(wrapper.vm.mdiDelete).toBeTruthy()
		expect(wrapper.vm.mdiCertificate).toBeTruthy()
		expect(wrapper.vm.mdiFileReplace).toBeTruthy()
		expect(wrapper.findComponent({ name: 'UploadCertificate' }).exists()).toBe(true)
		expect(wrapper.findComponent({ name: 'ReadCertificate' }).exists()).toBe(true)
	})

	it('calls UploadCertificate triggerUpload through ref safely', () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'certificate_engine') return 'openssl'
			if (key === 'config') return { hasSignatureFile: true }
			return fallback
		})
		hasSignatureFileMock.mockReturnValue(true)

		const wrapper = mount(ManagePassword, {
			global: {
				stubs: {
					NcButton: { template: '<button><slot /><slot name="icon" /></button>' },
					NcIconSvgWrapper: { name: 'NcIconSvgWrapper', props: ['path'], template: '<i class="icon" :data-path="path" />' },
					CreatePassword: true,
					ReadCertificate: true,
					ResetPassword: true,
					UploadCertificate: true,
				},
			},
		}) as ManagePasswordWrapper

		const triggerUpload = vi.fn()
		wrapper.vm.uploadCertificate = { triggerUpload }
		wrapper.vm.triggerUploadCertificate()

		expect(triggerUpload).toHaveBeenCalledTimes(1)
	})
})
