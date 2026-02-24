/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

const loadStateMock = vi.fn()
const hasSignatureFileMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('../../../../store/signMethods.js', () => ({
	useSignMethodsStore: () => ({
		hasSignatureFile: () => hasSignatureFileMock(),
		setHasSignatureFile: vi.fn(),
		showModal: vi.fn(),
	}),
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

let ManagePassword: unknown

beforeAll(async () => {
	;({ default: ManagePassword } = await import('../../../../views/Account/partials/ManagePassword.vue'))
})

describe('ManagePassword', () => {
	beforeEach(() => {
		loadStateMock.mockReset()
		hasSignatureFileMock.mockReset()
	})

	it('registers icon wrapper and exposes mdi icon paths used in template', () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'certificate_engine') return 'openssl'
			if (key === 'config') return { hasSignatureFile: true }
			return fallback
		})
		hasSignatureFileMock.mockReturnValue(true)

		const wrapper = mount(ManagePassword as never, {
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

		expect(wrapper.vm.$options.components.NcIconSvgWrapper).toBeTruthy()
		expect(wrapper.vm.mdiCloudUpload).toBeTruthy()
		expect(wrapper.vm.mdiLockOpenCheck).toBeTruthy()
		expect(wrapper.vm.mdiDelete).toBeTruthy()
		expect(wrapper.vm.mdiCertificate).toBeTruthy()
		expect(wrapper.vm.mdiFileReplace).toBeTruthy()
	})
})
