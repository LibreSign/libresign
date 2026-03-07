/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import CrlManagement from '../../../views/CrlManagement/CrlManagement.vue'

const axiosGetMock = vi.fn()
const axiosPostMock = vi.fn()
const showErrorMock = vi.fn()
const showSuccessMock = vi.fn()
const userConfigUpdateMock = vi.fn()

const userConfigStore = {
	crl_filters: {
		serialNumber: '',
		status: null,
		owner: '',
	},
	crl_sort: {
		sortBy: 'revoked_at',
		sortOrder: 'DESC',
	},
	update: vi.fn((...args: unknown[]) => userConfigUpdateMock(...args)),
}

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn((...args: unknown[]) => axiosGetMock(...args)),
		post: vi.fn((...args: unknown[]) => axiosPostMock(...args)),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string, params?: Record<string, string>) => {
		if (!params) {
			return `/ocs/v2.php${path}`
		}
		return `/ocs/v2.php${path.replace('{apiVersion}', params.apiVersion)}`
	}),
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn((...args: unknown[]) => showErrorMock(...args)),
	showSuccess: vi.fn((...args: unknown[]) => showSuccessMock(...args)),
}))

vi.mock('../../../store/userconfig.js', () => ({
	useUserConfigStore: vi.fn(() => userConfigStore),
}))

vi.mock('@nextcloud/vue/components/NcActions', () => ({
	default: { name: 'NcActions', template: '<div class="nc-actions-stub"><slot /><slot name="icon" /></div>' },
}))

vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({
	default: { name: 'NcIconSvgWrapper', template: '<i class="nc-icon-stub" />' },
}))

vi.mock('@nextcloud/vue/components/NcActionButton', () => ({
	default: {
		name: 'NcActionButton',
		emits: ['click', 'update:modelValue'],
		template: '<button class="nc-action-button-stub" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
	},
}))

vi.mock('@nextcloud/vue/components/NcActionInput', () => ({
	default: {
		name: 'NcActionInput',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<input class="nc-action-input-stub" />',
	},
}))

vi.mock('@nextcloud/vue/components/NcActionSeparator', () => ({
	default: { name: 'NcActionSeparator', template: '<hr class="nc-action-separator-stub" />' },
}))

vi.mock('@nextcloud/vue/components/NcAppContent', () => ({
	default: { name: 'NcAppContent', template: '<div class="nc-app-content-stub"><slot /></div>' },
}))

vi.mock('@nextcloud/vue/components/NcAvatar', () => ({
	default: { name: 'NcAvatar', template: '<div class="nc-avatar-stub" />' },
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		name: 'NcButton',
		emits: ['click'],
		template: '<button class="nc-button-stub" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
	},
}))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: {
		name: 'NcDialog',
		emits: ['update:open'],
		template: '<div class="nc-dialog-stub"><slot /></div>',
	},
}))

vi.mock('@nextcloud/vue/components/NcEmptyContent', () => ({
	default: { name: 'NcEmptyContent', template: '<div class="nc-empty-content-stub"><slot /><slot name="icon" /></div>' },
}))

vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
	default: { name: 'NcLoadingIcon', template: '<span class="nc-loading-icon-stub" />' },
}))

vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({
	default: { name: 'NcNoteCard', template: '<div class="nc-note-card-stub"><slot /></div>' },
}))

vi.mock('@nextcloud/vue/components/NcSelect', () => ({
	default: {
		name: 'NcSelect',
		props: ['modelValue', 'options'],
		emits: ['update:modelValue'],
		template: '<div class="nc-select-stub" />',
	},
}))

vi.mock('@nextcloud/vue/components/NcTextArea', () => ({
	default: {
		name: 'NcTextArea',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<textarea class="nc-text-area-stub" />',
	},
}))

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
	default: {
		name: 'NcTextField',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<input class="nc-text-field-stub" />',
	},
}))

describe('CrlManagement.vue', () => {
	const sampleEntry = {
		serial_number: 'ABC123',
		owner: 'Alice',
		status: 'issued',
		engine: 'openssl',
		certificate_type: 'leaf',
		issued_at: '2026-03-01T10:00:00Z',
		valid_to: '2026-12-31T10:00:00Z',
		revoked_at: null,
		reason_code: 0,
		comment: '',
	}

	const createWrapper = () => mount(CrlManagement)

	beforeEach(() => {
		axiosGetMock.mockReset()
		axiosPostMock.mockReset()
		showErrorMock.mockReset()
		showSuccessMock.mockReset()
		userConfigUpdateMock.mockReset()
		userConfigStore.update.mockClear()
		userConfigStore.crl_filters = { serialNumber: '', status: null, owner: '' }
		userConfigStore.crl_sort = { sortBy: 'revoked_at', sortOrder: 'DESC' }

		axiosGetMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						data: [sampleEntry],
						total: 1,
					},
				},
			},
		})

		axiosPostMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						success: true,
					},
				},
			},
		})
	})

	it('loads CRL entries on mount using saved filters and sort', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		expect(axiosGetMock).toHaveBeenCalledWith('/ocs/v2.php/apps/libresign/api/v1/crl/list', {
			params: {
				page: 1,
				length: 50,
				sortBy: 'revoked_at',
				sortOrder: 'DESC',
			},
		})
		expect(wrapper.vm.entries).toHaveLength(1)
		expect(wrapper.vm.hasMore).toBe(false)
	})

	it('computes active filters and persists filter updates', async () => {
		vi.useFakeTimers()
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.filters.serialNumber = 'XYZ'
		wrapper.vm.filters.owner = 'Bob'
		wrapper.vm.onFilterChange()
		vi.runAllTimers()
		await flushPromises()

		expect(wrapper.vm.hasActiveFilters).toBe(true)
		expect(wrapper.vm.activeFilterCount).toBe(2)
		expect(userConfigUpdateMock).toHaveBeenCalledWith('crl_filters', {
			serialNumber: 'XYZ',
			status: null,
			owner: 'Bob',
		})
		vi.useRealTimers()
	})

	it('toggles sort direction and then clears sort for the same column', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.sortBy = 'owner'
		wrapper.vm.sortOrder = 'DESC'
		await wrapper.vm.sortColumn('owner')

		expect(wrapper.vm.sortOrder).toBe('ASC')

		await wrapper.vm.sortColumn('owner')

		expect(wrapper.vm.sortBy).toBeNull()
		expect(wrapper.vm.sortOrder).toBeNull()
		expect(userConfigUpdateMock).toHaveBeenCalledWith('crl_sort', {
			sortBy: null,
			sortOrder: null,
		})
	})

	it('opens the CA warning dialog before revoking a root certificate', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.openRevokeDialog({ ...sampleEntry, certificate_type: 'root' })

		expect(wrapper.vm.caWarningDialog.open).toBe(true)
		expect(wrapper.vm.caWarningDialog.typeLabel).toBe('ROOT')

		wrapper.vm.proceedToRevokeDialog()

		expect(wrapper.vm.caWarningDialog.open).toBe(false)
		expect(wrapper.vm.revokeDialog.open).toBe(true)
		expect(wrapper.vm.revokeDialog.reasonCode).toEqual(wrapper.vm.reasonCodeOptions[0])
	})

	it('revokes a certificate and refreshes the list on success', async () => {
		const wrapper = createWrapper()
		await flushPromises()
		axiosGetMock.mockClear()

		wrapper.vm.revokeDialog.open = true
		wrapper.vm.revokeDialog.entry = sampleEntry
		wrapper.vm.revokeDialog.reasonCode = wrapper.vm.reasonCodeOptions[1]
		wrapper.vm.revokeDialog.reasonText = 'Compromised'

		await wrapper.vm.confirmRevoke()
		await flushPromises()

		expect(axiosPostMock).toHaveBeenCalledWith('/ocs/v2.php/apps/libresign/api/v1/crl/revoke', {
			serialNumber: 'ABC123',
			reasonCode: 1,
			reasonText: 'Compromised',
		})
		expect(showSuccessMock).toHaveBeenCalledWith('Certificate revoked successfully')
		expect(axiosGetMock).toHaveBeenCalledTimes(1)
		expect(wrapper.vm.revokeDialog.open).toBe(false)
	})

	it('formats missing dates and maps unknown reason codes safely', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.vm.formatDate(null)).toBe('-')
		expect(wrapper.vm.getReasonText(999)).toBe('Unknown')
		expect(wrapper.vm.getCertificateTypeLabel('intermediate')).toBe('Intermediate Certificate (CA)')
	})
})