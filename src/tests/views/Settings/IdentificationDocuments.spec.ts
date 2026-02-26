/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const loadStateMock = vi.fn()
const axiosGetMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: (...args: unknown[]) => axiosGetMock(...args),
	},
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

const OCP = {
	AppConfig: {
		setValue: vi.fn(),
	},
}

;(globalThis as typeof globalThis & { OCP: typeof OCP }).OCP = OCP

let IdentificationDocuments: unknown

beforeAll(async () => {
	;({ default: IdentificationDocuments } = await import('../../../views/Settings/IdentificationDocuments.vue'))
})

describe('IdentificationDocuments', () => {
	beforeEach(() => {
		loadStateMock.mockReset()
		axiosGetMock.mockReset()
		OCP.AppConfig.setValue.mockClear()
	})

	it('saves groups on update:modelValue', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'identification_documents') {
				return true
			}
			if (key === 'approval_group') {
				return []
			}
			return fallback
		})

		axiosGetMock.mockImplementation((url: string) => {
			if (url.includes('cloud/groups/details')) {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: [
									{ id: 'grpA', displayname: 'Group A' },
								],
							},
						},
					},
				})
			}
			return Promise.resolve({ data: { ocs: { data: {} } } })
		})

		const wrapper = mount(IdentificationDocuments as never, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcCheckboxRadioSwitch: { template: '<div><slot /></div>' },
					NcSelect: {
						name: 'NcSelect',
						props: ['modelValue'],
						emits: ['update:modelValue', 'search-change'],
						template: '<div class="nc-select-stub" />',
					},
				},
			},
		})
		await flushPromises()

		const ncSelect = wrapper.findComponent({ name: 'NcSelect' })
		ncSelect.vm.$emit('update:modelValue', [{ id: 'grpA', displayname: 'Group A' }])
		await flushPromises()

		expect(OCP.AppConfig.setValue).toHaveBeenCalledWith('libresign', 'approval_group', '["grpA"]')
	})
})
