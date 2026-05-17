/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

const axiosGetMock = vi.fn().mockResolvedValue({
	data: {
		ocs: {
			data: {
				groups: [],
				users: {},
			},
		},
	},
})

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: (...args: unknown[]) => axiosGetMock(...args),
	},
}))

const getPolicy = vi.fn((key: string) => {
	if (key === 'signature_flow') {
		return { effectiveValue: 'ordered_numeric' }
	}

	return null
})
const fetchSystemPolicy = vi.fn().mockResolvedValue(null)
const fetchGroupPolicy = vi.fn().mockResolvedValue(null)
const fetchUserPolicyForUser = vi.fn().mockResolvedValue(null)

vi.mock('../../../store/policies', () => ({
	usePoliciesStore: () => ({
		getPolicy,
		fetchEffectivePolicies: vi.fn().mockResolvedValue(undefined),
		fetchSystemPolicy,
		fetchGroupPolicy,
		fetchUserPolicyForUser,
		saveSystemPolicy: vi.fn().mockResolvedValue(undefined),
		saveGroupPolicy: vi.fn().mockResolvedValue(undefined),
		saveUserPreference: vi.fn().mockResolvedValue(undefined),
	}),
}))

import RealPolicyWorkbench from '../../../views/Settings/PolicyWorkbench/Catalog/Catalog.vue'

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
					props: ['name', 'buttons', 'size'],
					template: '<div class="dialog" :data-size="size"><h2 v-if="name" class="dialog-title">{{ name }}</h2><slot /><div v-if="buttons" class="dialog-footer"><button v-for="button in buttons" :key="button.label" :disabled="button.disabled" @click="button.callback()">{{ button.label }}</button></div></div>',
				},
				NcChip: { template: '<button class="nc-chip-stub">{{ text }}</button>', props: ['text'] },
				NcCheckboxRadioSwitch: {
					props: ['modelValue', 'type', 'name', 'value'],
					template: "<label class=\"nc-checkbox-radio-switch-stub\"><input :checked=\"type === 'radio' ? modelValue === value : modelValue\" :type=\"type === 'radio' ? 'radio' : 'checkbox'\" :name=\"name\" @change=\"$emit('update:modelValue', type === 'radio' ? value : $event.target.checked)\" /><span><slot /></span></label>",
				},
				NcSelectUsers: {
					props: ['placeholder', 'ariaLabel'],
					template: '<div class="nc-select-users-stub"><label>{{ ariaLabel }}</label><span>{{ placeholder }}</span><button type="button" class="nc-select-users-stub__select" @click="$emit(\'update:modelValue\', [{ id: \'user-1\' }])">Select target</button></div>',
				},
				NcActions: {
					props: ['open', 'ariaLabel'],
					emits: ['update:open'],
					template: '<div class="nc-actions-stub"><button class="nc-actions-stub__trigger" :aria-label="ariaLabel" @click="$emit(\'update:open\', !open)"><slot name="icon" /></button><div v-if="open" class="nc-actions-stub__menu"><slot /></div></div>',
				},
				NcActionButton: { template: '<button @click="$emit(\'click\')"><slot /></button>' },
				SignatureFooterRuleEditor: {
					name: 'SignatureFooterRuleEditor',
					props: ['inheritedTemplate'],
					template: '<div class="signature-footer-rule-editor-stub">Inherited template: {{ inheritedTemplate }}</div>',
				},
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

function findConfigureButtonForSetting(wrapper: ReturnType<typeof mountWorkbench>, settingTitle: string) {
	const settingCard = wrapper.findAll('article').find((article) => article.text().includes(settingTitle))
	if (!settingCard) {
		return undefined
	}

	return settingCard.findAll('button').find((button) => button.text().includes('Configure'))
}

describe('RealPolicyWorkbench.vue', () => {
	beforeEach(() => {
		getPolicy.mockReset()
		axiosGetMock.mockClear()
		fetchSystemPolicy.mockReset().mockResolvedValue(null)
		fetchGroupPolicy.mockReset().mockResolvedValue(null)
		fetchUserPolicyForUser.mockReset().mockResolvedValue(null)
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_flow') {
				return { effectiveValue: 'ordered_numeric' }
			}

			return null
		})
	})

	it('keeps rule creation inside a modal multi-step flow', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')
		await findButtonByText(wrapper, 'Create rule')?.trigger('click')

		expect(wrapper.findAll('.dialog-title').some((title) => title.text() === 'What do you want to create?')).toBe(true)

		const createScopeDialog = wrapper.find('.policy-workbench__create-scope-dialog')
		expect(createScopeDialog.exists()).toBe(true)

		const text = createScopeDialog.text()
		expect(text).toContain('User')
		expect(text).toContain('Group')
		expect(text).not.toContain('Instance')
		expect(text).not.toContain('Where do you want to apply this rule?')

		await findButtonContainingText(wrapper, 'User')?.trigger('click')

		const editorModal = wrapper.find('.policy-workbench__editor-modal-body')
		expect(editorModal.exists()).toBe(true)
		const editorText = editorModal.text()
		expect(editorText).toContain('Priority: User > Group > Default')
		expect(editorText).not.toContain('This rule overrides group and default settings for selected users.')
		expect(editorText).toContain('Target users')
		expect(editorText).toContain('Search users')
		expect(editorText).toContain('Simultaneous (Parallel)')
		expect(editorText).toContain('Sequential')
		expect(editorText).toContain('User choice')
		expect(wrapper.text()).toContain('← Back')
		expect(wrapper.text()).toContain('Cancel')
		expect(editorText).not.toContain('Instance default rule')
		expect(wrapper.find('.policy-workbench__editor-aside').exists()).toBe(false)

		await findButtonContainingText(wrapper, 'Back')?.trigger('click')
		expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
	})

	it('opens the editor directly in edit mode without the type selection step', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
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
		expect(editorText).toContain('Priority: User > Group > Default')
		expect(editorText).not.toContain('This sets the default signing order for everyone.')
		expect(wrapper.text()).toContain('Save changes')
		expect(wrapper.text()).toContain('Cancel')
		expect(wrapper.text()).not.toContain('← Back')
	})

	it('shows remove action for instance default rules', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_flow') {
				return { effectiveValue: 'ordered_numeric', sourceScope: 'global' }
			}

			return null
		})

		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		const actionsTrigger = wrapper.find('button[aria-label="Rule actions"]')
		expect(actionsTrigger.exists()).toBe(true)
		await actionsTrigger.trigger('click')

		const removeButton = wrapper.findAll('.nc-actions-stub__menu button').find((button) => button.text() === 'Remove')
		expect(removeButton).toBeTruthy()
	})

	it('allows reopening create flow after canceling a draft', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		const toolbarCreateRuleButton = wrapper.find('button.policy-workbench__crud-create-cta')
		expect(toolbarCreateRuleButton.exists()).toBe(true)
		await toolbarCreateRuleButton.trigger('click')
		expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)

		await findButtonContainingText(wrapper, 'User')?.trigger('click')
		expect(wrapper.find('.policy-workbench__editor-modal-body').exists()).toBe(true)

		const dialogCancelButton = wrapper.findAll('.dialog-footer button').find((button) => button.text() === 'Cancel')
		expect(dialogCancelButton).toBeTruthy()
		await dialogCancelButton?.trigger('click')
		await Promise.resolve()

		expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(false)

		const toolbarCreateRuleButtonAfterSave = wrapper.find('button.policy-workbench__crud-create-cta')
		expect(toolbarCreateRuleButtonAfterSave.exists()).toBe(true)
		expect(toolbarCreateRuleButtonAfterSave.attributes('disabled')).toBeUndefined()
		await toolbarCreateRuleButtonAfterSave.trigger('click')
		expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
	})

	it('shows instance option in create rule when only system default is active', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_flow') {
				return { effectiveValue: 'ordered_numeric', sourceScope: 'system' }
			}

			return null
		})

		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await wrapper.find('button.policy-workbench__crud-create-cta').trigger('click')

		const createScopeDialog = wrapper.find('.policy-workbench__create-scope-dialog')
		expect(createScopeDialog.exists()).toBe(true)
		expect(createScopeDialog.text()).toContain('Everyone')
	})

	it('shows unified default summary in system default mode', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_flow') {
				return { effectiveValue: 'none', sourceScope: 'system' }
			}

			return null
		})

		const wrapper = mountWorkbench()
		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		const text = wrapper.text()
		expect(text).toContain('Choose whether documents are signed in order or all at once.')
		expect(text).toContain('Default:')
		expect(text).toContain('User choice')
		expect(text).toContain('(default)')
		expect(text).toContain('Change')
		expect(text).not.toContain('Effective result:')
		expect(text).not.toContain('No instance default is configured. This setting currently uses the system default.')
	})

	it('uses large outer editor dialog for signature footer', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signature footer')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await findButtonByText(wrapper, 'Create rule')?.trigger('click')
		await findButtonContainingText(wrapper, 'User')?.trigger('click')

		expect(wrapper.findAll('.dialog[data-size="large"]').length).toBeGreaterThan(0)
		expect(wrapper.text()).toContain('Inherited template:')
	})

	it('hides allow-lower-level-customization toggle for request access by group', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Request access by group')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await findButtonByText(wrapper, 'Create rule')?.trigger('click')

		const createScopeDialog = wrapper.find('.policy-workbench__create-scope-dialog')
		expect(createScopeDialog.exists()).toBe(true)

		const groupScopeButton = createScopeDialog.findAll('button').find((button) => button.text().includes('Group'))
		expect(groupScopeButton).toBeTruthy()
		await groupScopeButton?.trigger('click')

		const editorModal = wrapper.find('.policy-workbench__editor-modal-body')
		expect(editorModal.exists()).toBe(true)
		expect(editorModal.text()).not.toContain('Allow lower-level customization')
	})

	it('shows signing order with sophisticated visual interface: filter, toggle, counts, and scopes', async () => {
		const wrapper = mountWorkbench()
		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		const text = wrapper.text()

		// Validate scope filter action is available in search actions area
		expect(wrapper.find('button[aria-label="Filter rules by scope"]').exists()).toBe(true)

		// Validate search/filter UI exists
		expect(wrapper.find('input[type="text"]').exists()).toBe(true)
		expect(text).toContain('Find setting')

		// Validate settings count display is hidden
		expect(text).not.toContain('Showing 2 settings')

		// Validate toggle button exists for card/list view
		expect(wrapper.find('.policy-workbench__catalog-view-button').exists()).toBe(true)

		// Validate signing order is displayed with compact header copy
		expect(text).toContain('Signing order')
		expect(text).toContain('Choose whether documents are signed in order or all at once.')

		// Validate default summary block content for custom default mode
		expect(text).toContain('Default:')
		expect(text).toContain('Sequential')
		expect(text).toContain('(custom)')
		expect(text).toContain('Change')
		expect(text).toContain('Priority: User > Group > Default')
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
		expect(text).toContain('Custom rules:none')
		expect(text).not.toContain('Custom rules active')

		// Validate migrated settings are present in the workbench catalog
		expect(text).toContain('Confetti animation')
		expect(text).toContain('Identification factors')
	})

	it('closes the rule actions menu after clicking edit', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
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

