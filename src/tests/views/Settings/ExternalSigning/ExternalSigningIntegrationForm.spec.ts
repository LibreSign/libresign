/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import ExternalSigningIntegrationForm from '../../../../views/Settings/ExternalSigning/ExternalSigningIntegrationForm.vue'
import { ExternalSigningAdminError } from '../../../../services/externalSigningAdminOcs'
import type { ExternalSigningDriver, ExternalSigningInstance } from '../../../../services/externalSigningAdmin'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

const driver: ExternalSigningDriver = {
	id: 'csc-v1', name: 'CSC v1', description: 'Test', experimental: true, available: true, unavailableReason: null,
	fields: [
		{ key: 'baseUrl', label: 'CSC service URL', type: 'url', secret: false, required: true, validation: { schemes: ['https'], allowUserInfo: false } },
		{ key: 'accessToken', label: 'CSC service access token', type: 'password', secret: true, required: false, validation: {} },
	],
}
const existing: ExternalSigningInstance = {
	id: 'one', driverId: 'csc-v1', name: 'Existing', enabled: false, configuration: { baseUrl: 'https://example.invalid' }, configuredSecretFields: ['accessToken'], revision: 4,
}

function createWrapper(save = vi.fn().mockResolvedValue(existing)) {
	return mount(ExternalSigningIntegrationForm, {
		props: { driver, existing, save },
		global: { stubs: {
			NcButton: { props: ['disabled', 'type'], template: '<button :type="type || \'button\'" :disabled="disabled"><slot /></button>' },
		} },
	})
}

describe('ExternalSigningIntegrationForm', () => {
	it('uses native Nextcloud text fields with stable labels for dynamic connection settings', () => {
		const wrapper = createWrapper()
		expect(wrapper.find('#external-signing-instance-name').exists()).toBe(true)
		expect(wrapper.find('#external-signing-field-baseUrl').exists()).toBe(true)
		expect(wrapper.find('#external-signing-field-accessToken').exists()).toBe(true)
		wrapper.unmount()
	})

	it('does not display an existing token or submit a replacement during ordinary edits', async () => {
		const save = vi.fn().mockResolvedValue(existing)
		const wrapper = createWrapper(save)
		expect(wrapper.text()).toContain('Secret configured')
		expect(wrapper.find<HTMLInputElement>('input[type="password"]').element.value).toBe('')
		await wrapper.find('form').trigger('submit')
		await flushPromises()
		expect(save).toHaveBeenCalledWith(expect.objectContaining({ secretReplacements: {}, revision: 4 }))
	})
	it('sends only explicitly replaced secrets', async () => {
		const save = vi.fn().mockResolvedValue(existing)
		const wrapper = createWrapper(save)
		await wrapper.find('input[type="password"]').setValue('synthetic-token')
		await wrapper.find('form').trigger('submit')
		await flushPromises()
		expect(save).toHaveBeenCalledWith(expect.objectContaining({ secretReplacements: { accessToken: 'synthetic-token' } }))
	})
	it('shows server mutation failures without claiming success', async () => {
		const wrapper = createWrapper(vi.fn().mockRejectedValue(new Error('synthetic conflict')))
		await wrapper.find('form').trigger('submit')
		await flushPromises()
		expect(wrapper.text()).toContain('Could not save the integration')
		expect(wrapper.emitted('saved')).toBeUndefined()
	})

	it('clears an optional stored secret only after explicit confirmation', async () => {
		const save = vi.fn().mockResolvedValue(existing)
		const wrapper = createWrapper(save)
		const clear = wrapper.find('input[type="checkbox"]')
		expect(clear.exists()).toBe(true)
		await clear.setValue(true)
		expect(wrapper.find<HTMLInputElement>('input[type="password"]').element.disabled).toBe(true)
		await wrapper.find('form').trigger('submit')
		await flushPromises()
		expect(save).toHaveBeenCalledWith(expect.objectContaining({ secretReplacements: { accessToken: null }, revision: 4 }))
	})

	it('reports a real revision conflict without claiming a successful save', async () => {
		const wrapper = createWrapper(vi.fn().mockRejectedValue(new ExternalSigningAdminError(409)))
		await wrapper.find('form').trigger('submit')
		await flushPromises()
		expect(wrapper.text()).toContain('changed by another administrator')
		expect(wrapper.emitted('saved')).toBeUndefined()
	})
})
