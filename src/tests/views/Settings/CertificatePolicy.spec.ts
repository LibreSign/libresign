/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import axios from '@nextcloud/axios'
import CertificatePolicy from '../../../views/Settings/CertificatePolicy.vue'
import * as viewer from '../../../utils/viewer.js'

const loadStateMock = vi.fn()

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

vi.mock('../../../utils/viewer.js', () => ({
	openDocument: vi.fn(),
}))

describe('CertificatePolicy.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		loadStateMock.mockImplementation((_app: string, key: string) => {
			if (key === 'certificate_policies_oid') {
				return '1.2.3'
			}

			if (key === 'certificate_policies_cps') {
				return '/apps/files/cps.pdf'
			}

			return ''
		})
	})

	function createWrapper() {
		return mount(CertificatePolicy, {
			global: {
				stubs: {
					NcButton: { template: '<button><slot /><slot name="icon" /></button>' },
					NcNoteCard: { template: '<div><slot /></div>' },
					NcTextField: true,
					NcIconSvgWrapper: true,
					NcLoadingIcon: true,
				},
			},
		})
	}

	it('emits the initial validity on mount', () => {
		const wrapper = createWrapper()

		expect(wrapper.emitted('certificate-policy-valid')).toEqual([[true]])
	})

	it('opens the current CPS document in the viewer', () => {
		const wrapper = createWrapper()

		wrapper.vm.view()

		expect(viewer.openDocument).toHaveBeenCalledWith({
			fileUrl: '/apps/files/cps.pdf',
			filename: 'Certificate Policy',
			nodeId: 0,
		})
	})

	it('persists the OID and toggles the success flag', async () => {
		vi.useFakeTimers()
		vi.mocked(axios.post).mockResolvedValue({ data: {} })
		const wrapper = createWrapper()

		wrapper.vm.OID = '2.5.4.3'
		await wrapper.vm._saveOID()

		expect(axios.post).toHaveBeenCalledWith('/apps/libresign/api/v1/admin/certificate-policy/oid', {
			oid: '2.5.4.3',
		})
		expect(wrapper.vm.dislaySuccessOID).toBe(true)

		await vi.advanceTimersByTimeAsync(2000)
		expect(wrapper.vm.dislaySuccessOID).toBe(false)

		vi.useRealTimers()
	})

	it('removes the CPS and emits the updated validity', async () => {
		vi.mocked(axios.delete).mockResolvedValue({ data: {} })
		const wrapper = createWrapper()

		await wrapper.vm.removeCps()
		await flushPromises()

		expect(axios.delete).toHaveBeenCalledWith('/apps/libresign/api/v1/admin/certificate-policy')
		expect(wrapper.vm.CPS).toBe('')
		expect(wrapper.emitted('certificate-policy-valid')?.at(-1)).toEqual([false])
	})
})
