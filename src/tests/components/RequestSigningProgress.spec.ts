/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import type { components } from '../../types/openapi/openapi'
import RequestSigningProgress from '../../components/RequestSigningProgress.vue'

vi.mock('../../utils/fileStatus.js', () => ({
	getStatusIcon: vi.fn(),
}))

type SigningProgress = components['schemas']['ProgressPayload']

describe('RequestSigningProgress business rules', () => {
	it('detects in-progress status only for status 5', async () => {
		const wrapper = shallowMount(RequestSigningProgress, {
			props: {
				status: 5,
			},
			global: { stubs: { NcIconSvgWrapper: true } },
		})
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.isInProgress).toBe(true)

		await wrapper.setProps({ status: 3 })
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.isInProgress).toBe(false)
	})

	it('exposes status icon path or empty string', async () => {
		const { getStatusIcon } = await import('../../utils/fileStatus.js')
		vi.mocked(getStatusIcon).mockReturnValueOnce('icon-path')
		const wrapper = shallowMount(RequestSigningProgress, {
			props: {
				status: 2,
			},
			global: { stubs: { NcIconSvgWrapper: true } },
		})
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.statusIconPath).toBe('icon-path')

		vi.mocked(getStatusIcon).mockReturnValueOnce(undefined)
		await wrapper.setProps({ status: 4 })
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.statusIconPath).toBe('')
	})

	it('returns 0% when progress missing or total is zero', async () => {
		const wrapper = shallowMount(RequestSigningProgress, {
			props: {
				status: 5,
				progress: null,
			},
			global: { stubs: { NcIconSvgWrapper: true } },
		})
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.progressPercentage).toBe(0)

		await wrapper.setProps({ progress: { total: 0, signed: 0, inProgress: 0, pending: 0 } })
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.progressPercentage).toBe(0)
	})

	it('rounds progress percentage from signed/total', async () => {
		const wrapper = shallowMount(RequestSigningProgress, {
			props: {
				status: 5,
				progress: { total: 3, signed: 2, inProgress: 1, pending: 0 },
			},
			global: { stubs: { NcIconSvgWrapper: true } },
		})
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.progressPercentage).toBe(67)
	})

	it('treats OpenAPI signer records as signed only when timestamp exists', async () => {
		const progress: SigningProgress = {
			total: 2,
			signed: 1,
			inProgress: 0,
			pending: 1,
			signers: [
				{ id: 1, displayName: 'Alice', signed: '2026-03-15T10:00:00Z', status: 3 },
				{ id: 2, displayName: 'Bob', signed: null, status: 1 },
			],
		}
		const wrapper = shallowMount(RequestSigningProgress, {
			props: {
				status: 5,
				progress,
			},
			global: { stubs: { NcIconSvgWrapper: true } },
		})
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.isProgressSignerSigned(progress.signers![0])).toBe(true)
		expect(wrapper.vm.isProgressSignerSigned(progress.signers![1])).toBe(false)
	})

	it('uses OpenAPI progress files with numeric ids and status text', async () => {
		const progress: SigningProgress = {
			total: 1,
			signed: 0,
			inProgress: 1,
			pending: 0,
			files: [
				{ id: 99, name: 'contract.pdf', status: 5, statusText: 'Signing in progress' },
			],
		}
		const wrapper = shallowMount(RequestSigningProgress, {
			props: {
				status: 5,
				progress,
			},
			global: { stubs: { NcIconSvgWrapper: true } },
		})
		await wrapper.vm.$nextTick()

		expect(wrapper.find('.signing-progress__file-name').text()).toBe('contract.pdf')
		expect(wrapper.find('.signing-progress__file-progress').text()).toBe('Signing in progress')
		expect(wrapper.vm.isProgressFileSigned(progress.files![0])).toBe(false)
	})
})
