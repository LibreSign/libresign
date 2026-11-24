/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import ExternalSigningAdmin from '../../../../views/Settings/ExternalSigning/ExternalSigningAdmin.vue'
import { ExternalSigningAdminError } from '../../../../services/externalSigningAdminOcs'
import type { ExternalSigningAdminAdapter, ExternalSigningDriver } from '../../../../services/externalSigningAdmin'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())
vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: () => ({ isAdmin: true }),
}))

const driver: ExternalSigningDriver = {
	id: 'csc-v1',
	name: 'CSC',
	description: 'Test driver',
	experimental: true,
	available: false,
	unavailableReason: 'not_installed',
	fields: [],
}

const adapter: ExternalSigningAdminAdapter = {
	list: async () => [],
	save: async () => { throw new Error('not invoked') },
	setEnabled: async () => { throw new Error('not invoked') },
	remove: async () => { throw new Error('not invoked') },
}

function mountAdmin(options: { adapter?: ExternalSigningAdminAdapter, layout?: 'cards' | 'compact', catalogLoader?: () => Promise<ExternalSigningDriver[]> } = {}) {
	return mount(ExternalSigningAdmin, {
		props: {
			catalogLoader: async () => [driver],
			...options,
		},
		global: {
			stubs: {
				NcButton: {
					props: ['disabled'],
					emits: ['click'],
					template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
				},
				NcDialog: {
					template: '<div role="dialog"><slot /></div>',
				},
			},
		},
	})
}

describe('ExternalSigningAdmin', () => {
	it('does not open an unusable dialog when no CRUD adapter exists', async () => {
		const wrapper = mountAdmin()
		await flushPromises()

		expect(wrapper.find('[data-cy="external-signing-admin"]').exists()).toBe(true)
		expect(wrapper.text()).toContain('Integration management is not connected yet.')
		expect(wrapper.find('button').attributes('disabled')).toBeDefined()
		await wrapper.find('button').trigger('click')
		expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
		expect(wrapper.find('.policy-workbench__setting-tile').exists()).toBe(false)
	})

	it('opens the real administrative dialog when an adapter is supplied', async () => {
		const list = vi.fn().mockResolvedValue([])
		const wrapper = mountAdmin({ adapter: { ...adapter, list } })
		await flushPromises()

		expect(wrapper.find('button').attributes('disabled')).toBeUndefined()
		wrapper.findComponent(NcButton).vm.$emit('click')
		await flushPromises()
		expect(wrapper.findComponent(NcDialog).exists()).toBe(true)
		expect(list).toHaveBeenCalledOnce()
	})

	it('reports unresolved signing sessions using the actual server conflict response', async () => {
		const instance = {
			id: 'a'.repeat(32),
			driverId: 'csc-v1',
			name: 'Synthetic CSC',
			configuration: { baseUrl: 'https://csc.example.org' },
			enabled: true,
			configuredSecretFields: [],
			revision: 3,
			driverAvailable: true,
		}
		const remove = vi.fn().mockRejectedValue(new ExternalSigningAdminError(409))
		const wrapper = mountAdmin({ adapter: { ...adapter, list: async () => [instance], remove } })
		await flushPromises()
		wrapper.findComponent(NcButton).vm.$emit('click')
		await flushPromises()
		const removeButton = wrapper.findAll('button').find(button => button.text().includes('Remove'))
		expect(removeButton).toBeDefined()
		vi.stubGlobal('confirm', vi.fn().mockReturnValue(true))
		try {
			await removeButton!.trigger('click')
			await flushPromises()
			expect(remove).toHaveBeenCalledOnce()
			expect(remove).toHaveBeenCalledWith(instance)
			expect(wrapper.text()).toContain('unresolved signing sessions')
			expect(wrapper.text()).not.toContain('Integration updated.')
		} finally {
			vi.unstubAllGlobals()
			wrapper.unmount()
		}
	})

	it('uses compact presentation without introducing policy semantics', async () => {
		const wrapper = mountAdmin({ layout: 'compact' })
		await flushPromises()
		expect(wrapper.find('.external-signing-admin--compact').exists()).toBe(true)
		expect(wrapper.find('[role="button"]').exists()).toBe(false)
	})

	it('hides the administrative entry when driver discovery is unavailable', async () => {
		const wrapper = mountAdmin({ catalogLoader: async () => {
			throw new Error('gated')
		} })
		await flushPromises()
		expect(wrapper.find('[data-cy="external-signing-admin"]').exists()).toBe(false)
	})
})
