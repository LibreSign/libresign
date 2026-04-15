/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, nextTick } from 'vue'

import type { EffectivePolicyState, SignatureFlowMode } from '../../../types/index'
import { createL10nMock } from '../../testHelpers/l10n.js'

const fetchEffectivePoliciesMock = vi.fn()
const saveUserPreferenceMock = vi.fn()
const clearUserPreferenceMock = vi.fn()
const getPolicyMock = vi.fn<(policyKey: string) => EffectivePolicyState | null>()
const loadStateMock = vi.fn()

vi.mock('@nextcloud/l10n', () => createL10nMock())

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('../../../store/policies', () => ({
	usePoliciesStore: () => ({
		fetchEffectivePolicies: fetchEffectivePoliciesMock,
		saveUserPreference: saveUserPreferenceMock,
		clearUserPreference: clearUserPreferenceMock,
		getPolicy: getPolicyMock,
	}),
}))

const NcSettingsSection = defineComponent({
	name: 'NcSettingsSection',
	props: ['name', 'description'],
	template: '<section><h2>{{ name }}</h2><p>{{ description }}</p><slot /></section>',
})

const NcNoteCard = defineComponent({
	name: 'NcNoteCard',
	props: ['type'],
	template: '<div class="note-card"><slot /></div>',
})

const NcCheckboxRadioSwitch = defineComponent({
	name: 'NcCheckboxRadioSwitch',
	props: ['value', 'modelValue', 'type', 'disabled', 'name'],
	emits: ['update:modelValue'],
	template: '<label class="switch"><slot /></label>',
})

const NcButton = defineComponent({
	name: 'NcButton',
	props: ['type', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
})

const SignatureFooterRuleEditor = defineComponent({
	name: 'SignatureFooterRuleEditor',
	props: ['modelValue', 'inheritedTemplate'],
	emits: ['update:modelValue'],
	template: '<div class="signature-footer-rule-editor-stub">Footer editor</div>',
})

const SignatureFlowScalarRuleEditor = defineComponent({
	name: 'SignatureFlowScalarRuleEditor',
	props: ['modelValue', 'editorScope', 'editorMode'],
	emits: ['update:modelValue'],
	template: '<div class="signature-flow-rule-editor-stub">Flow editor</div>',
})

describe('Preferences view', () => {
	beforeEach(() => {
		loadStateMock.mockReset().mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'can_request_sign') {
				return true
			}

			return fallback
		})
		fetchEffectivePoliciesMock.mockReset().mockResolvedValue(undefined)
		saveUserPreferenceMock.mockReset().mockResolvedValue(undefined)
		clearUserPreferenceMock.mockReset().mockResolvedValue(undefined)
		getPolicyMock.mockReset().mockReturnValue({
			policyKey: 'signature_flow',
			effectiveValue: 'parallel',
			sourceScope: 'system',
			visible: true,
			editableByCurrentActor: true,
			allowedValues: ['parallel', 'ordered_numeric'],
			blockedBy: null,
			canSaveAsUserDefault: true,
			canUseAsRequestOverride: true,
			preferenceWasCleared: false,
			groupCount: 0,
			userCount: 0,
		})
	})

	async function createWrapper() {
		const { default: Preferences } = await import('../../../views/Preferences/Preferences.vue')
		return mount(Preferences, {
			global: {
				stubs: {
					NcSettingsSection,
					NcNoteCard,
					NcCheckboxRadioSwitch,
					NcButton,
					SignatureFlowScalarRuleEditor,
					SignatureFooterRuleEditor,
				},
			},
		})
	}

	it('loads effective policies on mount', async () => {
		await createWrapper()

		expect(fetchEffectivePoliciesMock).toHaveBeenCalledTimes(1)
	})

	it('hides all preferences when user cannot request signatures', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'can_request_sign') {
				return false
			}

			return fallback
		})

		const wrapper = await createWrapper()

		expect(wrapper.findComponent({ name: 'NcSettingsSection' }).exists()).toBe(false)
	})

	it('shows the effective signing order summary', async () => {
		const wrapper = await createWrapper()

		expect(wrapper.text()).toContain('Effective value')
		expect(wrapper.text()).toContain('Simultaneous (Parallel)')
		expect(wrapper.text()).toContain('Global default')
	})

	it('saves a user preference when requested', async () => {
		const wrapper = await createWrapper()

		await wrapper.vm.savePreference('ordered_numeric' as SignatureFlowMode)

		expect(saveUserPreferenceMock).toHaveBeenCalledWith('signature_flow', 'ordered_numeric')
	})

	it('clears a saved user preference', async () => {
		getPolicyMock.mockReturnValue({
			policyKey: 'signature_flow',
			effectiveValue: 'ordered_numeric',
			sourceScope: 'user',
			visible: true,
			editableByCurrentActor: true,
			allowedValues: ['parallel', 'ordered_numeric'],
			blockedBy: null,
			canSaveAsUserDefault: true,
			canUseAsRequestOverride: true,
			preferenceWasCleared: false,
			groupCount: 0,
			userCount: 0,
		})
		const wrapper = await createWrapper()

		await wrapper.vm.clearPreference()

		expect(clearUserPreferenceMock).toHaveBeenCalledWith('signature_flow')
	})

	it('shows an informational note when saving is blocked', async () => {
		getPolicyMock.mockReturnValue({
			policyKey: 'signature_flow',
			effectiveValue: 'parallel',
			sourceScope: 'user',
			visible: true,
			editableByCurrentActor: false,
			allowedValues: ['parallel'],
			blockedBy: 'group',
			canSaveAsUserDefault: false,
			canUseAsRequestOverride: false,
			preferenceWasCleared: false,
			groupCount: 0,
			userCount: 0,
		})
		const wrapper = await createWrapper()
		await nextTick()

		expect(wrapper.text()).toContain('does not allow saving a personal default')
	})

	it('does not render footer preference section when user customization is not allowed', async () => {
		getPolicyMock.mockImplementation((key: string) => {
			if (key === 'add_footer') {
				return {
					policyKey: 'add_footer',
					effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false}',
					sourceScope: 'system',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: false,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
					groupCount: 0,
					userCount: 0,
				}
			}

			return {
				policyKey: 'signature_flow',
				effectiveValue: 'parallel',
				sourceScope: 'system',
				visible: true,
				editableByCurrentActor: true,
				allowedValues: ['parallel', 'ordered_numeric'],
				blockedBy: null,
				canSaveAsUserDefault: false,
				canUseAsRequestOverride: true,
				preferenceWasCleared: false,
				groupCount: 0,
				userCount: 0,
			}
		})

		const wrapper = await createWrapper()

		expect(wrapper.text()).not.toContain('Signature footer')
		expect(wrapper.findComponent({ name: 'SignatureFooterRuleEditor' }).exists()).toBe(false)
	})

	it('renders footer preference section when user customization is allowed', async () => {
		getPolicyMock.mockImplementation((key: string) => {
			if (key === 'add_footer') {
				return {
					policyKey: 'add_footer',
					effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false}',
					sourceScope: 'system',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
					groupCount: 0,
					userCount: 0,
				}
			}

			if (key === 'docmdp') {
				return null
			}

			return {
				policyKey: 'signature_flow',
				effectiveValue: 'parallel',
				sourceScope: 'system',
				visible: true,
				editableByCurrentActor: true,
				allowedValues: ['parallel', 'ordered_numeric'],
				blockedBy: null,
				canSaveAsUserDefault: true,
				canUseAsRequestOverride: true,
				preferenceWasCleared: false,
				groupCount: 0,
				userCount: 0,
			}
		})

		const wrapper = await createWrapper()

		expect(wrapper.text()).toContain('Signature footer')
		expect(wrapper.findComponent({ name: 'SignatureFooterRuleEditor' }).exists()).toBe(true)
		expect(wrapper.findComponent({ name: 'SignatureFooterRuleEditor' }).props('showPreview')).toBeUndefined()
	})

	it('auto-saves footer preference changes and hides manual action buttons', async () => {
		getPolicyMock.mockImplementation((key: string) => {
			if (key === 'add_footer') {
				return {
					policyKey: 'add_footer',
					effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"Inherited footer template"}',
					sourceScope: 'system',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
					groupCount: 0,
					userCount: 0,
				}
			}

			return null
		})

		const wrapper = await createWrapper()
		await nextTick()

		expect(wrapper.text()).toContain('Signature footer')
		expect(wrapper.text()).not.toContain('Save as my default')
		expect(wrapper.text()).not.toContain('Update saved preference')
		expect(wrapper.text()).not.toContain('Clear saved preference')

		await wrapper.vm.onPreferenceChange('add_footer', '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"Changed template"}')

		expect(saveUserPreferenceMock).toHaveBeenCalledWith(
			'add_footer',
			'{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"Changed template"}',
		)
	})

	it('allows undo after footer auto-save and clears preference when there was no prior user default', async () => {
		getPolicyMock.mockImplementation((key: string) => {
			if (key === 'add_footer') {
				return {
					policyKey: 'add_footer',
					effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false}',
					sourceScope: 'system',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
					groupCount: 0,
					userCount: 0,
				}
			}

			return null
		})

		const wrapper = await createWrapper()
		await nextTick()

		wrapper.vm.onPreferenceChange('add_footer', '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"Changed template"}')
		await Promise.resolve()
		await nextTick()

		expect(saveUserPreferenceMock).toHaveBeenCalledWith(
			'add_footer',
			'{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"Changed template"}',
		)
		expect(wrapper.vm.isAutoSaveSavedFor('add_footer')).toBe(true)

		await wrapper.vm.undoAutoSaveByKey('add_footer')

		expect(clearUserPreferenceMock).toHaveBeenCalledWith('add_footer')
	})

	it('renders footer preference when a user preference already exists even if saving is blocked', async () => {
		getPolicyMock.mockImplementation((key: string) => {
			if (key === 'add_footer') {
				return {
					policyKey: 'add_footer',
					effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":false,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"Current user template"}',
					sourceScope: 'user',
					visible: true,
					editableByCurrentActor: false,
					allowedValues: [],
					blockedBy: 'group',
					canSaveAsUserDefault: false,
					canUseAsRequestOverride: false,
					preferenceWasCleared: false,
					groupCount: 0,
					userCount: 0,
				}
			}

			if (key === 'docmdp') {
				return null
			}

			return {
				policyKey: 'signature_flow',
				effectiveValue: 'parallel',
				sourceScope: 'system',
				visible: true,
				editableByCurrentActor: true,
				allowedValues: ['parallel', 'ordered_numeric'],
				blockedBy: null,
				canSaveAsUserDefault: true,
				canUseAsRequestOverride: true,
				preferenceWasCleared: false,
				groupCount: 0,
				userCount: 0,
			}
		})

		const wrapper = await createWrapper()

		expect(wrapper.text()).toContain('Signature footer')
		expect(wrapper.findComponent({ name: 'SignatureFooterRuleEditor' }).exists()).toBe(false)
		expect(wrapper.text()).toContain('does not allow saving a personal default')
	})
})
