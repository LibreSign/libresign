/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
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

const mountedCleanup: Array<() => void> = []

afterEach(async () => {
	// Allow the popover's asynchronous close to complete before detaching its focus target.
	await flushPromises()
	for (const cleanup of mountedCleanup.splice(0)) {
		cleanup()
	}
	await flushPromises()
})

function mountAdmin(options: { adapter?: ExternalSigningAdminAdapter, layout?: 'cards' | 'compact', catalogLoader?: () => Promise<ExternalSigningDriver[]> } = {}) {
	// Nextcloud dialogs and action menus may teleport into document.body.
	const host = document.createElement('div')
	document.body.appendChild(host)
	const wrapper = mount(ExternalSigningAdmin, {
		attachTo: host,
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
	mountedCleanup.push(() => {
		wrapper.unmount()
		host.remove()
	})
	return wrapper
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
		await wrapper.find('.external-signing-admin__footer button').trigger('click')
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
		await wrapper.find('.external-signing-admin__footer button').trigger('click')
		await flushPromises()
		await wrapper.findComponent({ name: 'NcActions' }).find('button').trigger('click')
		await flushPromises()
		const removeButton = wrapper.findAllComponents({ name: 'NcActionButton' }).find(button => button.text().includes('Remove'))
		expect(removeButton).toBeDefined()
		vi.stubGlobal('confirm', vi.fn().mockReturnValue(true))
		try {
			await removeButton!.find('button').trigger('click')
			await flushPromises()
			expect(remove).toHaveBeenCalledOnce()
			expect(remove).toHaveBeenCalledWith(instance)
			expect(document.body.querySelector('.external-signing-admin__dialog p[role="alert"]')?.textContent).toContain('unresolved signing sessions')
			expect(wrapper.text()).not.toContain('Integration updated.')
		} finally {
			vi.unstubAllGlobals()
		}
	})

	it('shows one accessible native action menu per integration and blocks unavailable drivers', async () => {
		const instance = {
			id: 'b'.repeat(32),
			driverId: 'csc-v1',
			name: 'Test CSC',
			configuration: {},
			enabled: false,
			configuredSecretFields: [],
			revision: 1,
			driverAvailable: false,
		}
		const setEnabled = vi.fn()
		const wrapper = mountAdmin({ adapter: { ...adapter, list: async () => [instance], setEnabled } })
		await flushPromises()
		await wrapper.find('.external-signing-admin__footer button').trigger('click')
		await flushPromises()

		const actions = wrapper.findComponent({ name: 'NcActions' })
		expect(actions.props('ariaLabel')).toBe('Actions for Test CSC')
		await actions.find('button').trigger('click')
		await flushPromises()
		const buttons = actions.findAllComponents({ name: 'NcActionButton' })
		expect(buttons.map(button => button.text())).toEqual(['Edit', 'Enable', 'Remove'])
		// Nextcloud's NcActionButton defaults to leaving its popover open.
		expect(buttons.map(button => button.props('closeAfterClick'))).toEqual([true, true, true])
		expect(buttons.map(button => button.findAllComponents({ name: 'NcIconSvgWrapper' }).length)).toEqual([1, 1, 1])
		expect(buttons[1].props('disabled')).toBe(true)
		await buttons[1].find('button').trigger('click')
		expect(setEnabled).not.toHaveBeenCalled()
	})

	it('allows disabling an enabled integration from its action menu', async () => {
		const instance = {
			id: 'c'.repeat(32),
			driverId: 'csc-v1',
			name: 'Active CSC',
			configuration: {},
			enabled: true,
			configuredSecretFields: [],
			revision: 2,
			driverAvailable: true,
		}
		const setEnabled = vi.fn().mockResolvedValue(undefined)
		const wrapper = mountAdmin({ adapter: { ...adapter, list: async () => [instance], setEnabled } })
		await flushPromises()
		await wrapper.find('.external-signing-admin__footer button').trigger('click')
		await flushPromises()

		await wrapper.findComponent({ name: 'NcActions' }).find('button').trigger('click')
		await flushPromises()
		const disableButton = wrapper.findAllComponents({ name: 'NcActionButton' }).find(button => button.text().includes('Disable'))
		expect(disableButton).toBeDefined()
		await disableButton!.find('button').trigger('click')
		await flushPromises()
		expect(setEnabled).toHaveBeenCalledWith(instance, false)
		expect(document.body.querySelector('.external-signing-admin__dialog p[role="status"]')?.textContent).toContain('Integration updated.')
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
