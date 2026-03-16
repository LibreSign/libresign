/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock, interpolateL10n } from '../../testHelpers/l10n.js'
import { flushPromises, mount } from '@vue/test-utils'

import TSA from '../../../views/Settings/TSA.vue'

const loadStateMock = vi.fn()
const confirmPasswordMock = vi.fn()
const axiosPostMock = vi.fn()
const axiosDeleteMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/password-confirmation', () => ({
	confirmPassword: (...args: unknown[]) => confirmPasswordMock(...args),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: (...args: unknown[]) => axiosPostMock(...args),
		delete: (...args: unknown[]) => axiosDeleteMock(...args),
	},
}))

vi.mock('@nextcloud/l10n', () => createL10nMock({
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

describe('TSA.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.useFakeTimers()
		loadStateMock.mockImplementation((_app: string, _key: string, fallback: unknown) => fallback)
		confirmPasswordMock.mockResolvedValue(undefined)
		axiosPostMock.mockResolvedValue({ data: { ocs: { data: {} } } })
		axiosDeleteMock.mockResolvedValue({ data: { ocs: { data: {} } } })
	})

	function createWrapper() {
		return mount(TSA, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<section><slot /></section>' },
					NcCheckboxRadioSwitch: { template: '<div><slot /></div>' },
					NcTextField: { template: '<input />' },
					NcPasswordField: { template: '<input />' },
					NcSelect: { template: '<div />' },
				},
			},
		})
	}

	it('loads the saved TSA configuration from initial state', () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'tsa_url') return 'https://tsa.example.test'
			if (key === 'tsa_policy_oid') return '1.2.3.4'
			if (key === 'tsa_auth_type') return 'basic'
			if (key === 'tsa_username') return 'admin'
			if (key === 'tsa_password') return 'secret'
			return fallback
		})

		const wrapper = createWrapper()

		expect(wrapper.vm.enabled).toBe(true)
		expect(wrapper.vm.tsa_url).toBe('https://tsa.example.test')
		expect(wrapper.vm.tsa_policy_oid).toBe('1.2.3.4')
		expect(wrapper.vm.tsa_auth_type).toBe('basic')
		expect(wrapper.vm.tsa_username).toBe('admin')
		expect(wrapper.vm.tsa_password).toBe('secret')
		expect(wrapper.vm.selectedAuthType).toEqual({ id: 'basic', label: 'Username / Password' })
	})

	it('clears credentials when authentication is switched back to none', async () => {
		const wrapper = createWrapper()
		wrapper.vm.tsa_auth_type = wrapper.vm.AUTH_TYPES.BASIC
		wrapper.vm.tsa_username = 'admin'
		wrapper.vm.tsa_password = 'secret'

		wrapper.vm.selectedAuthType = { id: 'none', label: 'Without authentication' }
		await vi.advanceTimersByTimeAsync(wrapper.vm.DEBOUNCE_DELAY)
		await flushPromises()

		expect(wrapper.vm.tsa_auth_type).toBe('none')
		expect(wrapper.vm.tsa_username).toBe('')
		expect(wrapper.vm.tsa_password).toBe('')
		expect(confirmPasswordMock).toHaveBeenCalledTimes(1)
		expect(axiosPostMock).toHaveBeenCalledWith('/apps/libresign/api/v1/admin/tsa', expect.objectContaining({
			tsa_auth_type: 'none',
			tsa_username: '',
			tsa_password: '',
		}))
	})

	it('validates TSA URL and policy OID fields', () => {
		const wrapper = createWrapper()

		wrapper.vm.validateField('tsa_url', 'invalid-url')
		expect(wrapper.vm.errors.tsa_url).toBe('Invalid URL')

		wrapper.vm.validateField('tsa_policy_oid', 'abc.def')
		expect(wrapper.vm.errors.tsa_policy_oid).toContain('Invalid OID format')
	})

	it('applies the default TSA URL when enabling an empty configuration', async () => {
		const wrapper = createWrapper()
		wrapper.vm.enabled = true
		wrapper.vm.tsa_url = ''

		await wrapper.vm.toggleTsa()

		expect(wrapper.vm.tsa_url).toBe(wrapper.vm.DEFAULT_TSA_URL)
		expect(confirmPasswordMock).toHaveBeenCalledTimes(1)
		expect(axiosPostMock).toHaveBeenCalledWith('/apps/libresign/api/v1/admin/tsa', expect.objectContaining({
			tsa_url: wrapper.vm.DEFAULT_TSA_URL,
		}))
	})

	it('clears the persisted configuration when disabling TSA', async () => {
		const wrapper = createWrapper()
		wrapper.vm.enabled = false

		await wrapper.vm.toggleTsa()

		expect(confirmPasswordMock).toHaveBeenCalledTimes(1)
		expect(axiosDeleteMock).toHaveBeenCalledWith('/apps/libresign/api/v1/admin/tsa')
	})

	it('maps backend validation errors to the affected fields', () => {
		const wrapper = createWrapper()

		wrapper.vm.handleSaveError({
			response: {
				status: 400,
				data: {
					ocs: {
						data: {
							message: 'Username and password are required for basic authentication',
						},
					},
				},
			},
		})

		expect(wrapper.vm.errors.tsa_username).toBe('Name is mandatory')
		expect(wrapper.vm.errors.tsa_password).toBe('Password is mandatory')
	})

	it('persists the TSA configuration through the admin endpoint', async () => {
		const wrapper = createWrapper()
		wrapper.vm.tsa_url = 'https://tsa.example.test'
		wrapper.vm.tsa_policy_oid = '1.2.3.4'
		wrapper.vm.tsa_auth_type = 'basic'
		wrapper.vm.tsa_username = 'admin'
		wrapper.vm.tsa_password = 'secret'

		await wrapper.vm.saveTsaConfig()
		await flushPromises()

		expect(confirmPasswordMock).toHaveBeenCalledTimes(1)
		expect(axiosPostMock).toHaveBeenCalledWith('/apps/libresign/api/v1/admin/tsa', {
			tsa_url: 'https://tsa.example.test',
			tsa_policy_oid: '1.2.3.4',
			tsa_auth_type: 'basic',
			tsa_username: 'admin',
			tsa_password: 'secret',
		})
		expect(wrapper.vm.loading).toBe(false)
	})

	it('clears the TSA configuration through the delete endpoint', async () => {
		const wrapper = createWrapper()
		wrapper.vm.tsa_url = 'https://tsa.example.test'
		wrapper.vm.tsa_policy_oid = '1.2.3.4'
		wrapper.vm.tsa_auth_type = 'basic'
		wrapper.vm.tsa_username = 'admin'
		wrapper.vm.tsa_password = 'secret'

		await wrapper.vm.clearTsaConfig()

		expect(confirmPasswordMock).toHaveBeenCalledTimes(1)
		expect(axiosDeleteMock).toHaveBeenCalledWith('/apps/libresign/api/v1/admin/tsa')
		expect(wrapper.vm.tsa_url).toBe('')
		expect(wrapper.vm.tsa_policy_oid).toBe('')
		expect(wrapper.vm.tsa_auth_type).toBe('none')
		expect(wrapper.vm.tsa_username).toBe('')
		expect(wrapper.vm.tsa_password).toBe('')
	})
})