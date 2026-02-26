/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import RequestSigningProgress from '../../components/RequestSigningProgress.vue'

vi.mock('../../utils/fileStatus.js', () => ({
	getStatusIcon: vi.fn(),
}))

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

		await wrapper.setProps({ progress: { total: 0, signed: 0 } })
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.progressPercentage).toBe(0)
	})

	it('rounds progress percentage from signed/total', async () => {
		const wrapper = shallowMount(RequestSigningProgress, {
			props: {
				status: 5,
				progress: { total: 3, signed: 2 },
			},
			global: { stubs: { NcIconSvgWrapper: true } },
		})
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.progressPercentage).toBe(67)
	})
})
