/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mdiFilterVariant } from '@mdi/js'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import RealPolicyWorkbench from '../../../views/Settings/PolicyWorkbench/Catalog/Catalog.vue'

type MockPolicyState = {
	effectiveValue: unknown
	sourceScope?: string
	editableByCurrentActor?: boolean
	canSaveAsUserDefault?: boolean
	visible?: boolean
	allowedValues?: unknown[]
	blockedBy?: string | null
	canUseAsRequestOverride?: boolean
	preferenceWasCleared?: boolean
	groupCount?: number
	userCount?: number
	everyoneCount?: number
	meta?: Record<string, unknown>
}

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

const { currentUserState } = vi.hoisted(() => ({
	currentUserState: {
		isAdmin: true,
	},
}))

const { configState } = vi.hoisted(() => ({
	configState: {
		can_manage_group_policies: true,
		manageable_policy_group_ids: [] as string[],
	},
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => currentUserState),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, key: string, defaultValue: unknown) => {
		if (key === 'config') {
			return configState
		}

		if (key === 'effective_policies') {
			return {
				policies: {
					identify_methods: {
						effectiveValue: [],
					},
					signature_stamp: {
						meta: {
							defaultSystemValue: JSON.stringify({
								template: 'Signed with LibreSign\n{{SignerCommonName}}\nIssuer: {{IssuerCommonName}}\nDate: {{ServerSignatureDate}}',
								template_font_size: 9.8,
								signature_font_size: 20,
								signature_width: 350,
								signature_height: 100,
								background_type: 'default',
								render_mode: 'default',
							}),
						},
					},
				},
			}
		}

		return defaultValue
	}),
}))

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

const getPolicy = vi.fn((key: string): MockPolicyState | null => {
	if (key === 'signature_flow') {
		return { effectiveValue: 'ordered_numeric' }
	}

	return null
})

const fetchSystemPolicy = vi.fn().mockResolvedValue(null)
const fetchGroupPolicy = vi.fn().mockResolvedValue(null)
const fetchUserPolicyForUser = vi.fn().mockResolvedValue(null)
const fetchEffectivePolicies = vi.fn().mockResolvedValue(undefined)
const saveSystemPolicy = vi.fn().mockResolvedValue(undefined)
const clearSystemPolicy = vi.fn().mockResolvedValue(undefined)
const saveGroupPolicy = vi.fn().mockResolvedValue(undefined)
const saveUserPreference = vi.fn().mockResolvedValue(undefined)

vi.mock('../../../store/policies', () => ({
	usePoliciesStore: () => ({
		getPolicy,
		fetchEffectivePolicies,
		fetchSystemPolicy,
		fetchGroupPolicy,
		fetchUserPolicyForUser,
		saveSystemPolicy,
		clearSystemPolicy,
		saveGroupPolicy,
		saveUserPreference,
	}),
}))

function mountWorkbench() {
	return mount(RealPolicyWorkbench, {
		global: {
			stubs: {
				NcSettingsSection: { template: '<div><slot /></div>' },
				NcTextField: { template: '<div><label>Find setting</label><input type="text" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>' },
				NcAppNavigationSearch: { template: '<div class="nc-app-navigation-search-stub"><input type="text" @input="$emit(\'update:modelValue\', $event.target.value)" /><div class="nc-app-navigation-search-stub__actions"><slot name="actions" /></div></div>' },
				NcButton: { template: '<button v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>' },
				NcIconSvgWrapper: { props: ['path'], template: '<span class="icon-stub" :data-path="path" />' },
				NcNoteCard: { template: '<div class="note-card"><slot /></div>' },
				NcDialog: {
					props: ['name', 'buttons', 'size'],
					data: () => ({
						open: true,
					}),
					watch: {
						name() {
							this.open = true
						},
					},
					methods: {
						requestClose() {
							this.$emit('closing')
							this.open = false
						},
					},
					template: '<div v-if="open" class="dialog" :data-size="size"><h2 v-if="name" class="dialog-title">{{ name }}</h2><button type="button" class="dialog-close-stub" @click="requestClose">Close</button><slot /><div v-if="buttons" class="dialog-footer"><button v-for="button in buttons" :key="button.label" :disabled="button.disabled" @click="button.callback()">{{ button.label }}</button></div></div>',
				},
				NcEmptyContent: {
					props: ['name', 'description'],
					template: '<div class="nc-empty-content-stub"><p class="nc-empty-content-stub__name">{{ name }}</p><p v-if="description" class="nc-empty-content-stub__description">{{ description }}</p><slot name="icon" /><div class="nc-empty-content-stub__action"><slot name="action" /></div></div>',
				},
				NcChip: { template: '<button class="nc-chip-stub">{{ text }}</button>', props: ['text'] },
				NcCheckboxRadioSwitch: {
					props: ['modelValue', 'type', 'name', 'value'],
					template: "<label class=\"nc-checkbox-radio-switch-stub\"><input :checked=\"type === 'radio' ? modelValue === value : modelValue\" :type=\"type === 'radio' ? 'radio' : 'checkbox'\" :name=\"name\" @change=\"$emit('update:modelValue', type === 'radio' ? value : $event.target.checked)\" /><span><slot /></span></label>",
				},
				NcSelectUsers: {
					props: ['placeholder', 'ariaLabel'],
					template: '<div class="nc-select-users-stub"><label>{{ ariaLabel }}</label><span>{{ placeholder }}</span><button type="button" class="nc-select-users-stub__select" @click="$emit(\'update:modelValue\', [{ id: { uid: \'user-1\' } }])">Select target</button></div>',
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

function findCreateRuleButton(wrapper: ReturnType<typeof mountWorkbench>) {
	return wrapper.find('button.policy-workbench__crud-create-cta').exists()
		? wrapper.find('button.policy-workbench__crud-create-cta')
		: wrapper.find('.nc-empty-content-stub__action button')
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
		currentUserState.isAdmin = true
		configState.can_manage_group_policies = true
		configState.manageable_policy_group_ids = []
		getPolicy.mockReset()
		axiosGetMock.mockClear()
		fetchSystemPolicy.mockReset().mockResolvedValue(null)
		fetchGroupPolicy.mockReset().mockResolvedValue(null)
		fetchUserPolicyForUser.mockReset().mockResolvedValue(null)
		fetchEffectivePolicies.mockReset().mockResolvedValue(undefined)
		saveSystemPolicy.mockReset().mockResolvedValue(undefined)
		clearSystemPolicy.mockReset().mockResolvedValue(undefined)
		saveGroupPolicy.mockReset().mockResolvedValue(undefined)
		saveUserPreference.mockReset().mockResolvedValue(undefined)
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

		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
		})

		expect(wrapper.findAll('.dialog-title').some((title) => title.text() === 'What do you want to create?')).toBe(true)

		const createScopeDialog = wrapper.find('.policy-workbench__create-scope-dialog')
		expect(createScopeDialog.exists()).toBe(true)

		const text = createScopeDialog.text()
		expect(text).toContain('Account')
		expect(text).toContain('Group')
		expect(text).not.toContain('Instance')
		expect(text).not.toContain('Where do you want to apply this rule?')

		const accountScopeButton = createScopeDialog.findAll('button').find((button) => button.text().includes('Account'))
		expect(accountScopeButton).toBeTruthy()
		await accountScopeButton?.trigger('click')

		const editorModal = wrapper.find('.policy-workbench__editor-modal-body')
		expect(editorModal.exists()).toBe(true)
		const editorText = editorModal.text()
		expect(editorText).toContain('Priority: Account > Group > Default')
		expect(wrapper.find('.policy-workbench__table-priority-note').exists()).toBe(true)
		expect(editorText).not.toContain('This rule overrides group and default settings for selected users.')
		expect(editorText).toContain('Scope accounts')
		expect(editorText).toContain('Search scope accounts')
		expect(editorText).toContain('Parallel')
		expect(editorText).toContain('Sequential')
		expect(editorText).toContain('Using instance default')
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

		await vi.waitFor(() => {
			expect(wrapper.find('button[aria-label="Rule actions"]').exists()).toBe(true)
		})
		const actionsTrigger = wrapper.find('button[aria-label="Rule actions"]')
		await actionsTrigger.trigger('click')

		const editButton = wrapper.findAll('.nc-actions-stub__menu button').find((button) => button.text() === 'Edit')
		expect(editButton).toBeTruthy()
		await editButton?.trigger('click')

		expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(false)
		expect(wrapper.find('.policy-workbench__editor-aside').exists()).toBe(false)

		const editorText = wrapper.find('.policy-workbench__editor-modal-body').text()
		expect(editorText).toContain('Priority: Account > Group > Default')
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

		await vi.waitFor(() => {
			expect(wrapper.find('button[aria-label="Rule actions"]').exists()).toBe(true)
		})
		const actionsTrigger = wrapper.find('button[aria-label="Rule actions"]')
		await actionsTrigger.trigger('click')

		const removeButton = wrapper.findAll('.nc-actions-stub__menu button').find((button) => button.text() === 'Remove')
		expect(removeButton).toBeTruthy()
	})

	it('does not crash when removal dialog closes while remove request is pending', async () => {
		let resolvePendingSystemRemoval: (() => void) | null = null
		saveSystemPolicy
			.mockImplementationOnce(() => new Promise<void>((resolve) => {
				resolvePendingSystemRemoval = resolve
			}))
			.mockResolvedValue(undefined)

		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await vi.waitFor(() => {
			expect(wrapper.find('button[aria-label="Rule actions"]').exists()).toBe(true)
		})
		await wrapper.find('button[aria-label="Rule actions"]').trigger('click')

		const removeButton = wrapper.findAll('.nc-actions-stub__menu button').find((button) => button.text() === 'Remove')
		expect(removeButton).toBeTruthy()
		await removeButton?.trigger('click')

		const confirmDialog = wrapper
			.findAll('.dialog')
			.find((dialog) => dialog.find('.dialog-title').text() === 'Confirm rule removal')
		expect(confirmDialog).toBeTruthy()

		const confirmButton = confirmDialog?.findAll('.dialog-footer button').find((button) => button.text() === 'Remove exception')
		expect(confirmButton).toBeTruthy()
		await confirmButton?.trigger('click')

		await vi.waitFor(() => {
			expect(saveSystemPolicy).toHaveBeenCalledWith('signature_flow', null, false)
		})

		await confirmDialog?.find('.dialog-close-stub').trigger('click')

		expect(resolvePendingSystemRemoval).toBeTypeOf('function')
		const resolvePendingSave = resolvePendingSystemRemoval as (() => void) | null
		resolvePendingSave?.()
		await Promise.resolve()
		await Promise.resolve()

		expect(wrapper.exists()).toBe(true)
	})

	it('allows reopening create flow after canceling a draft', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		const toolbarCreateRuleButton = wrapper.find('button.policy-workbench__crud-create-cta')
		expect(toolbarCreateRuleButton.exists()).toBe(true)
		await toolbarCreateRuleButton.trigger('click')
		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
		})
		const createScopeDialog = wrapper.find('.policy-workbench__create-scope-dialog')
		expect(createScopeDialog.exists()).toBe(true)

		const accountScopeButton = createScopeDialog.findAll('button').find((button) => button.text().includes('Account'))
		expect(accountScopeButton).toBeTruthy()
		await accountScopeButton?.trigger('click')
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
		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
		})
	})

	it('skips the scope chooser for group admins when account is the only creatable option', async () => {
		currentUserState.isAdmin = false
		configState.can_manage_group_policies = true
		configState.manageable_policy_group_ids = ['board']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_flow') {
				return {
					effectiveValue: 'ordered_numeric',
					sourceScope: 'system',
					editableByCurrentActor: false,
					canSaveAsUserDefault: true,
				}
			}

			return null
		})

		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await vi.waitFor(() => {
			expect(findCreateRuleButton(wrapper).exists()).toBe(true)
		})
		await findCreateRuleButton(wrapper).trigger('click')

		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__editor-modal-body').exists()).toBe(true)
		})

		expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(false)
		expect(wrapper.find('.policy-workbench__table-priority-note').exists()).toBe(false)
		expect(wrapper.findAll('.dialog-title').some((title) => title.text() === 'What do you want to create?')).toBe(false)
		expect(wrapper.text()).not.toContain('Blocked by the global default.')
		expect(wrapper.text()).not.toContain('Priority:')
		expect(wrapper.text()).not.toContain('← Back')
		expect(wrapper.text()).toContain('Create rule')
	})

	it('shows priority note without default for group admins who can manage group and account rules', async () => {
		currentUserState.isAdmin = false
		configState.can_manage_group_policies = true
		configState.manageable_policy_group_ids = ['board']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_flow') {
				return {
					effectiveValue: 'ordered_numeric',
					editableByCurrentActor: true,
					canSaveAsUserDefault: true,
				}
			}

			return null
		})

		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		const priorityNote = wrapper.find('.policy-workbench__table-priority-note')
		expect(priorityNote.exists()).toBe(true)
		expect(priorityNote.text()).toContain('Priority: Account > Group')
		expect(priorityNote.text()).not.toContain('Default')
		expect(wrapper.find('.policy-workbench__default-inline').exists()).toBe(true)
	})

	it('hides the priority note for identify methods when only account rules are actionable', async () => {
		currentUserState.isAdmin = false
		configState.can_manage_group_policies = true
		configState.manageable_policy_group_ids = ['board']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'identify_methods') {
				return {
					effectiveValue: {
						factors: [
							{
								name: 'account',
								enabled: true,
								requirement: 'required',
							},
						],
						minimumTotalVerifiedFactors: 1,
					},
					sourceScope: 'group',
					editableByCurrentActor: false,
					canSaveAsUserDefault: true,
				}
			}

			if (key === 'signature_flow') {
				return { effectiveValue: 'ordered_numeric' }
			}

			return null
		})

		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Identification factors')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await vi.waitFor(() => {
			expect(findCreateRuleButton(wrapper).exists()).toBe(true)
		})

		expect(wrapper.find('.policy-workbench__table-priority-note').exists()).toBe(false)
		expect(wrapper.find('.policy-workbench__default-inline').exists()).toBe(false)

		await findCreateRuleButton(wrapper).trigger('click')

		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__editor-modal-body').exists()).toBe(true)
		})

		expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(false)
		expect(wrapper.text()).not.toContain('Priority:')
	})

	it('shows the group option for identify methods when group admin manages multiple groups', async () => {
		currentUserState.isAdmin = false
		configState.can_manage_group_policies = true
		configState.manageable_policy_group_ids = ['board', 'legal']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'identify_methods') {
				return {
					effectiveValue: {
						factors: [
							{
								name: 'account',
								enabled: true,
								requirement: 'required',
							},
						],
						minimumTotalVerifiedFactors: 1,
					},
					sourceScope: 'group',
					editableByCurrentActor: false,
					canSaveAsUserDefault: true,
				}
			}

			if (key === 'signature_flow') {
				return { effectiveValue: 'ordered_numeric' }
			}

			return null
		})

		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Identification factors')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await vi.waitFor(() => {
			expect(findCreateRuleButton(wrapper).exists()).toBe(true)
		})

		await findCreateRuleButton(wrapper).trigger('click')

		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
		})

		const createScopeText = wrapper.find('.policy-workbench__create-scope-dialog').text()
		expect(createScopeText).toContain('Account')
		expect(createScopeText).toContain('Group')
		expect(createScopeText).not.toContain('Everyone')
	})

	it('keeps create-rule editor visible after dismissing discard dialog from ESC flow', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await wrapper.find('button.policy-workbench__crud-create-cta').trigger('click')
		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
		})
		const createScopeDialog = wrapper.find('.policy-workbench__create-scope-dialog')
		const accountScopeButton = createScopeDialog.findAll('button').find((button) => button.text().includes('Account'))
		expect(accountScopeButton).toBeTruthy()
		await accountScopeButton?.trigger('click')

		const firstHashAlgorithmInput = wrapper.find('input[type="radio"]')
		expect(firstHashAlgorithmInput.exists()).toBe(true)
		await firstHashAlgorithmInput.setValue(true)

		const createRuleDialog = wrapper
			.findAll('.dialog')
			.find((dialog) => dialog.find('.dialog-title').text() === 'Create rule')
		expect(createRuleDialog).toBeTruthy()
		await createRuleDialog?.find('.dialog-close-stub').trigger('click')

		const discardDialog = wrapper
			.findAll('.dialog')
			.find((dialog) => dialog.find('.dialog-title').text() === 'Discard unsaved changes?')
		expect(discardDialog).toBeTruthy()
		await discardDialog?.find('.dialog-close-stub').trigger('click')

		expect(
			wrapper
				.findAll('.dialog-title')
				.some((title) => title.text() === 'Create rule'),
		).toBe(true)
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

		await vi.waitFor(() => {
			expect(findCreateRuleButton(wrapper).exists()).toBe(true)
		})
		const createRuleButton = findCreateRuleButton(wrapper)
		expect(createRuleButton.exists()).toBe(true)
		await createRuleButton.trigger('click')
		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
		})

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
		expect(text).toContain('Using instance default')
		expect(text).toContain('(default)')
		expect(text).toContain('Change')
	})

	it('uses large outer editor dialog for signature footer', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signature footer')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await vi.waitFor(() => {
			expect(findCreateRuleButton(wrapper).exists()).toBe(true)
		})
		const createRuleButton = findCreateRuleButton(wrapper)
		expect(createRuleButton.exists()).toBe(true)
		await createRuleButton.trigger('click')
		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
		})
		const createScopeDialog = wrapper.find('.policy-workbench__create-scope-dialog')
		const accountScopeButton = createScopeDialog.findAll('button').find((button) => button.text().includes('Account'))
		expect(accountScopeButton).toBeTruthy()
		await accountScopeButton?.trigger('click')

		expect(wrapper.findAll('.dialog[data-size="large"]').length).toBeGreaterThan(0)
		expect(wrapper.text()).toContain('Inherited template:')
	})

	it('shows the group option for signature footer when group admin manages multiple groups', async () => {
		currentUserState.isAdmin = false
		configState.can_manage_group_policies = true
		configState.manageable_policy_group_ids = ['board', 'legal']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'add_footer') {
				return {
					effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false,"footerTemplate":"","previewWidth":595,"previewHeight":100,"previewZoom":100}',
					sourceScope: 'group',
					editableByCurrentActor: false,
					canSaveAsUserDefault: true,
				}
			}

			if (key === 'signature_flow') {
				return { effectiveValue: 'ordered_numeric' }
			}

			return null
		})

		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signature footer')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await vi.waitFor(() => {
			expect(findCreateRuleButton(wrapper).exists()).toBe(true)
		})

		await findCreateRuleButton(wrapper).trigger('click')

		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
		})

		const createScopeText = wrapper.find('.policy-workbench__create-scope-dialog').text()
		expect(createScopeText).toContain('Account')
		expect(createScopeText).toContain('Group')
		expect(createScopeText).not.toContain('Everyone')
	})

	it('shows the group option for signing order when group admin manages multiple groups', async () => {
		currentUserState.isAdmin = false
		configState.can_manage_group_policies = true
		configState.manageable_policy_group_ids = ['board', 'legal']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_flow') {
				return {
					effectiveValue: 'ordered_numeric',
					sourceScope: 'group',
					editableByCurrentActor: false,
					canSaveAsUserDefault: true,
				}
			}

			return null
		})

		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await vi.waitFor(() => {
			expect(findCreateRuleButton(wrapper).exists()).toBe(true)
		})

		await findCreateRuleButton(wrapper).trigger('click')

		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
		})

		const createScopeText = wrapper.find('.policy-workbench__create-scope-dialog').text()
		expect(createScopeText).toContain('Account')
		expect(createScopeText).toContain('Group')
		expect(createScopeText).not.toContain('Everyone')
	})

	it('shows the group option for signature stamp text when group admin manages multiple groups', async () => {
		currentUserState.isAdmin = false
		configState.can_manage_group_policies = true
		configState.manageable_policy_group_ids = ['board', 'legal']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_stamp') {
				return {
					effectiveValue: '{"template":"Signed with LibreSign","template_font_size":9.8,"signature_font_size":20,"signature_width":350,"signature_height":100,"background_type":"default","render_mode":"default"}',
					sourceScope: 'group',
					editableByCurrentActor: false,
					canSaveAsUserDefault: true,
				}
			}

			if (key === 'collect_metadata') {
				return { effectiveValue: false, sourceScope: 'system' }
			}

			if (key === 'signature_flow') {
				return { effectiveValue: 'ordered_numeric' }
			}

			return null
		})

		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signature stamp text')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await vi.waitFor(() => {
			expect(findCreateRuleButton(wrapper).exists()).toBe(true)
		})

		await findCreateRuleButton(wrapper).trigger('click')

		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
		})

		const createScopeText = wrapper.find('.policy-workbench__create-scope-dialog').text()
		expect(createScopeText).toContain('Account')
		expect(createScopeText).toContain('Group')
		expect(createScopeText).not.toContain('Everyone')
	})

	it('shows the group option for confetti animation when group admin manages multiple groups', async () => {
		currentUserState.isAdmin = false
		configState.can_manage_group_policies = true
		configState.manageable_policy_group_ids = ['board', 'legal']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'show_confetti_after_signing') {
				return {
					effectiveValue: true,
					sourceScope: 'group',
					editableByCurrentActor: false,
					canSaveAsUserDefault: true,
				}
			}

			if (key === 'signature_flow') {
				return { effectiveValue: 'ordered_numeric' }
			}

			return null
		})

		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Confetti animation')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await vi.waitFor(() => {
			expect(findCreateRuleButton(wrapper).exists()).toBe(true)
		})

		await findCreateRuleButton(wrapper).trigger('click')

		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(true)
		})

		const createScopeText = wrapper.find('.policy-workbench__create-scope-dialog').text()
		expect(createScopeText).toContain('Account')
		expect(createScopeText).toContain('Group')
		expect(createScopeText).not.toContain('Everyone')
	})

	it('does not render the system default request-access row for group-admins', async () => {
		currentUserState.isAdmin = false
		configState.can_manage_group_policies = true
		configState.manageable_policy_group_ids = ['board']

		getPolicy.mockImplementation((key: string) => {
			if (key === 'groups_request_sign') {
				return {
					effectiveValue: '{"allowGroups":["board"],"denyGroups":[]}',
					sourceScope: 'global',
					editableByCurrentActor: true,
					canSaveAsUserDefault: false,
					visible: true,
					allowedValues: [],
					blockedBy: null,
					canUseAsRequestOverride: false,
					preferenceWasCleared: false,
					groupCount: 0,
					userCount: 0,
					everyoneCount: 1,
				}
			}

			if (key === 'signature_flow') {
				return { effectiveValue: 'ordered_numeric' }
			}

			return null
		})

		fetchSystemPolicy.mockResolvedValue({
			policyKey: 'groups_request_sign',
			scope: 'global',
			value: '{"allowGroups":["board"],"denyGroups":[]}',
			allowChildOverride: true,
			visibleToChild: true,
			allowedValues: [],
		})

		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signature request access')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await vi.waitFor(() => {
			expect(fetchSystemPolicy).toHaveBeenCalledWith('groups_request_sign')
		})

		expect(wrapper.text()).not.toContain('Default (everyone)')
		expect(wrapper.findAll('button[aria-label="Rule actions"]')).toHaveLength(0)
		expect(wrapper.find('.policy-workbench__table').exists()).toBe(false)
	})

	it('shows signing order with sophisticated visual interface: filter, toggle, counts, and scopes', async () => {
		const wrapper = mountWorkbench()
		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		// Validate scope filter action is available in search actions area
		expect(wrapper.find('button[aria-label="Filter rules by scope"]').exists()).toBe(true)

		// Validate search/filter UI exists
		expect(wrapper.find('input[type="text"]').exists()).toBe(true)
		expect(wrapper.text()).toContain('Find setting')

		// Validate settings count display is hidden
		expect(wrapper.text()).not.toContain('Showing 2 settings')

		// Validate toggle button exists for card/list view
		expect(wrapper.find('.policy-workbench__catalog-view-button').exists()).toBe(true)

		// Validate signing order is displayed with compact header copy
		expect(wrapper.text()).toContain('Signing order')
		expect(wrapper.text()).toContain('Choose whether documents are signed in order or all at once.')

		// Validate default summary block content for custom default mode
		expect(wrapper.text()).toContain('Default:')
		expect(wrapper.text()).toContain('Sequential')
		expect(wrapper.text()).toContain('(custom)')
		expect(wrapper.text()).toContain('Change')
		expect(wrapper.text()).toContain('Priority: Account > Group > Default')
		expect(wrapper.find('.policy-workbench__table-priority-note').exists()).toBe(true)
		expect(wrapper.text()).not.toContain('Effective result:')

		await vi.waitFor(() => {
			expect(wrapper.findAll('th').length).toBeGreaterThan(0)
		})
		const text = wrapper.text()
		const tableHeaders = wrapper.findAll('th').map((header) => header.text())
		expect(tableHeaders).toContain('Type')
		expect(tableHeaders).toContain('Target')
		expect(tableHeaders).toContain('Value')
		expect(tableHeaders).toContain('Actions')
		expect(tableHeaders).not.toContain('Behavior')

		// Validate noisy inheritance warning is not shown by default
		expect(text).not.toContain('Some users may not allow user overrides because their group rule requires inheritance.')

		// Validate counts shown without the custom-rules badge when no scoped overrides exist
		expect(text).toContain('Custom rules:none')
		expect(text).toContain('Default access:Not configured')
		expect(text).toContain('Custom overrides:none configured')
		expect(text).not.toContain('Custom rules active')
		expect(text).not.toContain('Loading rules…')

		// Validate migrated settings are present in the workbench catalog
		expect(text).toContain('Confetti animation')
		expect(text).toContain('Identification factors')
	})

	it('closes the rule actions menu after clicking edit', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await vi.waitFor(() => {
			expect(wrapper.find('button[aria-label="Rule actions"]').exists()).toBe(true)
		})
		const actionsTrigger = wrapper.find('button[aria-label="Rule actions"]')
		await actionsTrigger.trigger('click')

		expect(wrapper.find('.nc-actions-stub__menu').exists()).toBe(true)

		const editButton = wrapper.findAll('.nc-actions-stub__menu button').find((button) => button.text() === 'Edit')
		expect(editButton).toBeTruthy()
		await editButton?.trigger('click')

		expect(wrapper.find('.nc-actions-stub__menu').exists()).toBe(false)
		expect(wrapper.text()).toContain('Edit rule')
	})

	it('shows filter empty state copy and icon when CRUD filters have no matches', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signing order')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		const scopeFilterTrigger = wrapper.find('button[aria-label="Filter rules by scope"]')
		expect(scopeFilterTrigger.exists()).toBe(true)
		await scopeFilterTrigger.trigger('click')

		const groupFilterButton = wrapper.findAll('.nc-actions-stub__menu button').find((button) => button.text() === 'Group')
		expect(groupFilterButton).toBeTruthy()
		await groupFilterButton?.trigger('click')

		await vi.waitFor(() => {
			expect(wrapper.find('.nc-empty-content-stub__name').text()).toBe('No rules match the current filters.')
		})

		expect(wrapper.find('.nc-empty-content-stub__description').text()).toBe('Try adjusting or clearing the current filters.')
		expect(wrapper.find('.policy-workbench__table-empty-content .icon-stub').attributes('data-path')).toBe(mdiFilterVariant)
	})

	it('shows one unified request expiration setting card', () => {
		const wrapper = mountWorkbench()
		const text = wrapper.text()

		expect(text).toContain('Request expiration')
		expect(text).toContain('Configure expiration and renewal timing for signing requests.')
		expect(text).not.toContain('Renewal interval in seconds of a subscription request.')
	})

	it('opens signature processing directly in the system-scope editor', async () => {
		const wrapper = mountWorkbench()

		const openPolicyButton = findConfigureButtonForSetting(wrapper, 'Signature processing')
		expect(openPolicyButton).toBeTruthy()
		await openPolicyButton?.trigger('click')

		await vi.waitFor(() => {
			expect(findCreateRuleButton(wrapper).exists()).toBe(true)
		})
		const createRuleButton = findCreateRuleButton(wrapper)
		expect(createRuleButton.exists()).toBe(true)
		await createRuleButton.trigger('click')

		await vi.waitFor(() => {
			expect(wrapper.find('.policy-workbench__editor-modal-body').exists()).toBe(true)
		})

		expect(wrapper.find('.policy-workbench__create-scope-dialog').exists()).toBe(false)
		expect(wrapper.text()).toContain('Create rule')
	})
})

