/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../testHelpers/l10n.js'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const getPolicy = vi.fn((key: string) => {
	if (key === 'signature_flow') {
		return { effectiveValue: 'ordered_numeric' }
	}

	return null
})

vi.mock('../../../store/policies', () => ({
	usePoliciesStore: () => ({
		getPolicy,
		fetchEffectivePolicies: vi.fn().mockResolvedValue(undefined),
		saveSystemPolicy: vi.fn().mockResolvedValue(undefined),
		saveGroupPolicy: vi.fn().mockResolvedValue(undefined),
		saveUserPreference: vi.fn().mockResolvedValue(undefined),
	}),
}))

import RealPolicyWorkbench from '../../../views/Settings/PolicyWorkbench/RealPolicyWorkbench.vue'

function mountWorkbench() {
	return mount(RealPolicyWorkbench, {
		global: {
			stubs: {
				NcSettingsSection: { template: '<div><slot /></div>' },
				NcTextField: { template: '<div><label>Find setting</label><input type="text" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>' },
				NcAppNavigationSearch: { template: '<div class="nc-app-navigation-search-stub"><input type="text" @input="$emit(\'update:modelValue\', $event.target.value)" /><div class="nc-app-navigation-search-stub__actions"><slot name="actions" /></div></div>' },
				NcButton: { template: '<button v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>' },
				NcIconSvgWrapper: { template: '<span class="icon-stub" />' },
				NcNoteCard: { template: '<div class="note-card"><slot /></div>' },
				NcDialog: {
					props: ['name', 'buttons'],
					template: '<div class="dialog"><h2 v-if="name" class="dialog-title">{{ name }}</h2><slot /><div v-if="buttons" class="dialog-footer"><button v-for="button in buttons" :key="button.label" :disabled="button.disabled" @click="button.callback()">{{ button.label }}</button></div></div>',
				},
				NcChip: { template: '<button class="nc-chip-stub">{{ text }}</button>', props: ['text'] },
				NcCheckboxRadioSwitch: {
					props: ['modelValue', 'type', 'name'],
					template: "<label class=\"nc-checkbox-radio-switch-stub\"><input :checked=\"modelValue\" :type=\"type === 'radio' ? 'radio' : 'checkbox'\" :name=\"name\" @change=\"$emit('update:modelValue', $event.target.checked)\" /><span><slot /></span></label>",
				},
				NcSelectUsers: {
					props: ['placeholder', 'ariaLabel'],
					template: '<div class="nc-select-users-stub"><label>{{ ariaLabel }}</label><span>{{ placeholder }}</span></div>',
				},
				NcActions: {
					props: ['open', 'ariaLabel'],
					emits: ['update:open'],
					template: '<div class="nc-actions-stub"><button class="nc-actions-stub__trigger" :aria-label="ariaLabel" @click="$emit(\'update:open\', !open)"><slot name="icon" /></button><div v-if="open" class="nc-actions-stub__menu"><slot /></div></div>',
				},
				NcActionButton: { template: '<button @click="$emit(\'click\')"><slot /></button>' },
			},
		},
	})
}

function findButtonByText(wrapper: ReturnType<typeof mountWorkbench>, text: string) {
	return wrapper.findAll('button').find((button) => button.text() === text)
}

function findButtonContainingText(wrapper: ReturnType<typeof mountWorkbench>, text: string) {
	return wrapper.findAll('button').find((button) => button.text().includes(text))
}

describe('RealPolicyWorkbench.vue', () => {
	beforeEach(() => {
		getPolicy.mockReset()
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_flow') {
				return { effectiveValue: 'ordered_numeric' }
			}

			return null
		})
	})

	it('keeps rule creation inside a modal multi-step flow', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = wrapper.findAll('button').find((button) => button.text().includes('Open policy'))
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')
		await findButtonByText(wrapper, 'Create rule')?.trigger('click')

		expect(wrapper.findAll('.dialog-title').some((title) => title.text() === 'What do you want to create?')).toBe(true)

		const createScopeDialog = wrapper.find('.policy-workbench__create-scope-dialog')
		expect(createScopeDialog.exists()).toBe(true)

		const text = createScopeDialog.text()
		expect(text).toContain('User')
		expect(text).toContain('Group')
		expect(text).toContain('Instance')
		expect(text).not.toContain('Where do you want to apply this rule?')

		await findButtonContainingText(wrapper, 'User')?.trigger('click')

		const editorModal = wrapper.find('.policy-workbench__editor-modal-body')
		expect(editorModal.exists()).toBe(true)
		const editorText = editorModal.text()
		expect(editorText).toContain('This rule overrides group and default settings for selected users.')
		expect(editorText).toContain('Target users')
		expect(editorText).toContain('Search users')
		expect(editorText).toContain('Simultaneous (Parallel)')
		expect(editorText).toContain('Sequential')
		expect(editorText).toContain('Let users choose')
		expect(wrapper.text()).toContain('← Back')
		expect(wrapper.text()).toContain('Cancel')
		expect(editorText).not.toContain('Instance default rule')
		expect(wrapper.find('.policy-workbench__editor-aside').exists()).toBe(false)

		await findButtonContainingText(wrapper, 'Back')?.trigger('click')
		expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
	})

	it('opens the editor directly in edit mode without the type selection step', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = wrapper.findAll('button').find((button) => button.text().includes('Open policy'))
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		const actionsTrigger = wrapper.find('button[aria-label="Rule actions"]')
		expect(actionsTrigger.exists()).toBe(true)
		await actionsTrigger.trigger('click')

		const editButton = wrapper.findAll('.nc-actions-stub__menu button').find((button) => button.text() === 'Edit')
		expect(editButton).toBeTruthy()
		await editButton?.trigger('click')

		expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(false)
		expect(wrapper.find('.policy-workbench__editor-aside').exists()).toBe(false)

		const editorText = wrapper.find('.policy-workbench__editor-modal-body').text()
		expect(editorText).toContain('This sets the default signing order for everyone.')
		expect(wrapper.text()).toContain('Save changes')
		expect(wrapper.text()).toContain('Cancel')
		expect(wrapper.text()).not.toContain('← Back')
	})

	it('shows unified default summary in system default mode', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_flow') {
				return { effectiveValue: 'none', sourceScope: 'system' }
			}

			return null
		})

		const wrapper = mountWorkbench()
		const openPolicyButton = wrapper.findAll('button').find((button) => button.text().includes('Open policy'))
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		const text = wrapper.text()
		expect(text).toContain('Control how signers complete documents.')
		expect(text).toContain('Default:')
		expect(text).toContain('Let users choose')
		expect(text).toContain('(system)')
		expect(text).toContain('Change')
		expect(text).not.toContain('Effective result:')
		expect(text).not.toContain('No instance default is configured. This setting currently uses the system default.')
	})

	it('shows signing order with sophisticated visual interface: filter, toggle, counts, and scopes', async () => {
		const wrapper = mountWorkbench()
		const openPolicyButton = wrapper.findAll('button').find((button) => button.text().includes('Open policy'))
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		const text = wrapper.text()

		// Validate scope filter action is available in search actions area
		expect(wrapper.find('button[aria-label="Filter rules by scope"]').exists()).toBe(true)

		// Validate search/filter UI exists
		expect(wrapper.find('input[type="text"]').exists()).toBe(true)
		expect(text).toContain('Find setting')

		// Validate settings count display
		expect(text).toContain('1 of 1 settings visible')

		// Validate toggle button exists for card/list view
		expect(wrapper.find('.policy-workbench__catalog-view-button').exists()).toBe(true)

		// Validate signing order is displayed with compact header copy
		expect(text).toContain('Signing order')
		expect(text).toContain('Control how signers complete documents.')

		// Validate default summary block content for custom default mode
		expect(text).toContain('Default:')
		expect(text).toContain('Sequential')
		expect(text).toContain('(custom)')
		expect(text).toContain('Change')
		expect(text).not.toContain('Effective result:')

		const tableHeaders = wrapper.findAll('th').map((header) => header.text())
		expect(tableHeaders).toContain('Type')
		expect(tableHeaders).toContain('Target')
		expect(tableHeaders).toContain('Value')
		expect(tableHeaders).toContain('Actions')
		expect(tableHeaders).not.toContain('Behavior')

		// Validate noisy inheritance warning is not shown by default
		expect(text).not.toContain('Some users may not allow user overrides because their group rule requires inheritance.')

		// Validate counts shown
		expect(text).toContain('Group overrides: 0')
		expect(text).toContain('User overrides: 0')

		// Validate POC settings are NOT present
		expect(text).not.toContain('Confetti')
		expect(text).not.toContain('Identification factors')
	})

	it('closes the rule actions menu after clicking edit', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = wrapper.findAll('button').find((button) => button.text().includes('Open policy'))
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		const actionsTrigger = wrapper.find('button[aria-label="Rule actions"]')
		expect(actionsTrigger.exists()).toBe(true)
		await actionsTrigger.trigger('click')

		expect(wrapper.find('.nc-actions-stub__menu').exists()).toBe(true)

		const editButton = wrapper.findAll('.nc-actions-stub__menu button').find((button) => button.text() === 'Edit')
		expect(editButton).toBeTruthy()
		await editButton?.trigger('click')

		expect(wrapper.find('.nc-actions-stub__menu').exists()).toBe(false)
		expect(wrapper.text()).toContain('Edit rule')
	})

})

