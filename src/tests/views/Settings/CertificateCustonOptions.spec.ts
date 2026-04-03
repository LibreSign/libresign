/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { interpolateL10n } from '../../testHelpers/l10n.js'
import { mount } from '@vue/test-utils'

import CertificateCustonOptions from '../../../views/Settings/CertificateCustonOptions.vue'

type CertificateOption = {
	id: string
	value: string | string[]
	error?: boolean
}

type CertificateCustonOptionsVm = InstanceType<typeof CertificateCustonOptions> & {
	certificateList: CertificateOption[]
	customNamesOptions: Array<{ id: string }>
	onOptionalAttributeSelect: (selected: { id: string }) => Promise<void>
	addArrayEntry: (id: string) => void
	removeArrayEntry: (id: string, index: number) => void
	validate: (id: string) => void
	removeOptionalAttribute: (id: string) => Promise<void>
}

const emitMock = vi.fn()

vi.mock('@nextcloud/event-bus', () => ({
	emit: (...args: unknown[]) => emitMock(...args),
}))

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n({
	t: (_app: string, text: string, vars?: Record<string, string | number>) => interpolateL10n(text, vars),
	n: (_app: string, singular: string, plural: string, count: number, vars?: Record<string, string | number>) => {
		const template = count === 1 ? singular : plural
		return interpolateL10n(template, { count, ...(vars ?? {}) })
	},
	translate: (_app: string, text: string, vars?: Record<string, string | number>) => interpolateL10n(text, vars),
	translatePlural: (_app: string, singular: string, plural: string, count: number, vars?: Record<string, string | number>) => {
		const template = count === 1 ? singular : plural
		return interpolateL10n(template, { count, ...(vars ?? {}) })
	},
}))

describe('CertificateCustonOptions.vue', () => {
	beforeEach(() => {
		emitMock.mockReset()
	})

	function createWrapper(names: CertificateOption[] = []) {
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
		const vm = wrapper.vm as CertificateCustonOptionsVm

		expect(vm.certificateList).toEqual([{ id: 'O', value: 'LibreSign' }])
		expect(vm.customNamesOptions.map((option) => option.id)).not.toContain('O')
		expect(vm.customNamesOptions.map((option) => option.id)).not.toContain('CN')
		expect(vm.customNamesOptions.map((option) => option.id)).toContain('OU')
	})

	it('adds OU as an array-backed option', async () => {
		const wrapper = createWrapper()
		const vm = wrapper.vm as CertificateCustonOptionsVm

		await vm.onOptionalAttributeSelect({ id: 'OU' })

		expect(vm.certificateList).toEqual([
			expect.objectContaining({
				id: 'OU',
				value: [''],
			}),
		])
	})

	it('emits updated list when validating and editing OU array entries', async () => {
		const wrapper = createWrapper([{ id: 'OU', value: ['Security'] }])
		const vm = wrapper.vm as CertificateCustonOptionsVm

		await vm.addArrayEntry('OU')
		expect(emitMock).toHaveBeenLastCalledWith('libresign:update:certificateToSave', [
			{ id: 'OU', value: ['Security', ''] },
		])

		await vm.removeArrayEntry('OU', 1)
		expect(emitMock).toHaveBeenLastCalledWith('libresign:update:certificateToSave', [
			{ id: 'OU', value: ['Security'] },
		])
	})

	it('marks invalid values and emits sanitized payload on validate', async () => {
		const wrapper = createWrapper([{ id: 'C', value: 'B' }])
		const vm = wrapper.vm as CertificateCustonOptionsVm

		await vm.validate('C')

		expect(vm.certificateList[0].error).toBe(true)
		expect(emitMock).toHaveBeenLastCalledWith('libresign:update:certificateToSave', [
			{ id: 'C', value: 'B' },
		])
	})

	it('removes selected custom attributes from the local list', async () => {
		const wrapper = createWrapper([
			{ id: 'O', value: 'LibreSign' },
			{ id: 'OU', value: ['Security'] },
		])
		const vm = wrapper.vm as CertificateCustonOptionsVm

		await vm.removeOptionalAttribute('O')

		expect(vm.certificateList).toEqual([
			{ id: 'OU', value: ['Security'] },
		])
	})
})