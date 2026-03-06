/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import CertificateCustonOptions from '../../../views/Settings/CertificateCustonOptions.vue'

const emitMock = vi.fn()

vi.mock('@nextcloud/event-bus', () => ({
	emit: (...args: unknown[]) => emitMock(...args),
}))

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string, vars?: Record<string, string | number>) => {
		if (!vars) {
			return text
		}
		return Object.entries(vars).reduce((message, [key, value]) => message.replace(`{${key}}`, String(value)), text)
	}),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	isRTL: vi.fn(() => false),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
}))

describe('CertificateCustonOptions.vue', () => {
	beforeEach(() => {
		emitMock.mockReset()
	})

	function createWrapper(names: Array<Record<string, unknown>> = []) {
		return mount(CertificateCustonOptions, {
			props: { names },
			global: {
				stubs: {
					NcButton: { template: '<button><slot /><slot name="icon" /></button>' },
					NcIconSvgWrapper: true,
					NcListItem: { template: '<div><slot name="subname" /><slot /></div>' },
					NcPopover: { template: '<div><slot name="trigger" /><slot /></div>' },
					NcTextField: {
						props: ['modelValue'],
						emits: ['update:modelValue'],
						template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
					},
				},
			},
		})
	}

	it('initializes certificateList from names and filters already selected options', () => {
		const wrapper = createWrapper([{ id: 'O', value: 'LibreSign' }])

		expect(wrapper.vm.certificateList).toEqual([{ id: 'O', value: 'LibreSign' }])
		expect(wrapper.vm.customNamesOptions.map((option: { id: string }) => option.id)).not.toContain('O')
		expect(wrapper.vm.customNamesOptions.map((option: { id: string }) => option.id)).not.toContain('CN')
		expect(wrapper.vm.customNamesOptions.map((option: { id: string }) => option.id)).toContain('OU')
	})

	it('adds OU as an array-backed option', async () => {
		const wrapper = createWrapper()

		await wrapper.vm.onOptionalAttributeSelect({ id: 'OU' })

		expect(wrapper.vm.certificateList).toEqual([
			expect.objectContaining({
				id: 'OU',
				value: [''],
			}),
		])
	})

	it('emits updated list when validating and editing OU array entries', async () => {
		const wrapper = createWrapper([{ id: 'OU', value: ['Security'] }])

		await wrapper.vm.addArrayEntry('OU')
		expect(emitMock).toHaveBeenLastCalledWith('libresign:update:certificateToSave', [
			{ id: 'OU', value: ['Security', ''] },
		])

		await wrapper.vm.removeArrayEntry('OU', 1)
		expect(emitMock).toHaveBeenLastCalledWith('libresign:update:certificateToSave', [
			{ id: 'OU', value: ['Security'] },
		])
	})

	it('marks invalid values and emits sanitized payload on validate', async () => {
		const wrapper = createWrapper([{ id: 'C', value: 'B' }])

		await wrapper.vm.validate('C')

		expect(wrapper.vm.certificateList[0].error).toBe(true)
		expect(emitMock).toHaveBeenLastCalledWith('libresign:update:certificateToSave', [
			{ id: 'C', value: 'B' },
		])
	})

	it('removes selected custom attributes from the local list', async () => {
		const wrapper = createWrapper([
			{ id: 'O', value: 'LibreSign' },
			{ id: 'OU', value: ['Security'] },
		])

		await wrapper.vm.removeOptionalAttribute('O')

		expect(wrapper.vm.certificateList).toEqual([
			{ id: 'OU', value: ['Security'] },
		])
	})
})