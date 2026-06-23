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

const NcIconSvgWrapper = defineComponent({
	name: 'NcIconSvgWrapper',
	template: '<span class="icon-wrapper" />',
})

const NcLoadingIcon = defineComponent({
	name: 'NcLoadingIcon',
	template: '<span class="loading-icon" />',
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

const SignatureTextRuleEditor = defineComponent({
	name: 'SignatureTextRuleEditor',
	props: ['modelValue', 'editorScope', 'editorMode'],
	emits: ['update:modelValue'],
	template: '<div class="signature-text-rule-editor-stub">Signature stamp editor</div>',
})

function createPolicyState(overrides: Partial<EffectivePolicyState> & Pick<EffectivePolicyState, 'policyKey'>): EffectivePolicyState {
	const { policyKey, ...rest } = overrides

	return {
		policyKey,
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
		everyoneCount: 0,
		...rest,
	}
}

const signatureStampPreferenceValue = JSON.stringify({
	template: 'Signed by {{SignerCommonName}}',
	template_font_size: 9.8,
	signature_font_size: 20,
	signature_width: 350,
	signature_height: 100,
	background_type: 'default',
	render_mode: 'GRAPHIC_AND_DESCRIPTION',
})

const signatureStampEditorValue = JSON.stringify({
	template: 'Signed by {{SignerCommonName}}',
	template_font_size: 9.8,
	signature_font_size: 20,
	signature_width: 350,
	signature_height: 100,
	background_type: 'default',
	render_mode: 'default',
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
			everyoneCount: 0,
		})
	})

	async function createWrapper() {
		vi.resetModules()
		const { default: Preferences } = await import('../../../views/Preferences/Preferences.vue')
		return mount(Preferences, {
			global: {
				stubs: {
					NcSettingsSection,
					NcNoteCard,
					NcCheckboxRadioSwitch,
					NcButton,
					NcIconSvgWrapper,
					NcLoadingIcon,
					SignatureFlowScalarRuleEditor,
					SignatureFooterRuleEditor,
					SignatureTextRuleEditor,
				},
			},
		})
	}

	it('loads effective policies on mount', async () => {
		await createWrapper()
		await nextTick()

		expect(fetchEffectivePoliciesMock).toHaveBeenCalledTimes(1)
	}, 30000)

	it('renders preferences when personal defaults are allowed even if user cannot create signature requests', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'can_request_sign') {
				return false
			}

			return fallback
		})

		const wrapper = await createWrapper()
		await nextTick()

		expect(wrapper.findComponent({ name: 'NcSettingsSection' }).exists()).toBe(true)
		expect(wrapper.text()).toContain('Signing order')
	})

	it('renders signing order section without verbose summary labels', async () => {
		const wrapper = await createWrapper()
		await nextTick()

		expect(wrapper.text()).toContain('Signing order')
		expect(wrapper.text()).not.toContain('Effective value')
		expect(wrapper.text()).not.toContain('Source')
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
			everyoneCount: 0,
		})
		const wrapper = await createWrapper()

		await wrapper.vm.clearPreference()

		expect(clearUserPreferenceMock).toHaveBeenCalledWith('signature_flow')
	})

	it('refreshes effective policies after reset to avoid stale local value', async () => {
		let currentPolicy: EffectivePolicyState = {
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
			everyoneCount: 0,
		}
		let shouldApplyCanonicalAfterFetch = false

		getPolicyMock.mockImplementation(() => currentPolicy)
		clearUserPreferenceMock.mockImplementation(async () => {
			currentPolicy = {
				...currentPolicy,
				sourceScope: 'user_policy',
				// Simulate a stale DELETE response payload that still carries the previous effective value.
				effectiveValue: 'ordered_numeric',
			}
			shouldApplyCanonicalAfterFetch = true
		})
		fetchEffectivePoliciesMock.mockImplementation(async () => {
			if (!shouldApplyCanonicalAfterFetch) {
				return
			}
			currentPolicy = {
				...currentPolicy,
				effectiveValue: 'parallel',
			}
		})

		const wrapper = await createWrapper()
		await nextTick()

		expect(wrapper.vm.selectedPreferenceValues.signature_flow).toBe('ordered_numeric')

		await wrapper.vm.undoAutoSaveByKey('signature_flow')
		await nextTick()

		expect(clearUserPreferenceMock).toHaveBeenCalledWith('signature_flow')
		expect(fetchEffectivePoliciesMock).toHaveBeenCalledTimes(2)
		expect(wrapper.vm.selectedPreferenceValues.signature_flow).toBe('parallel')
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
			everyoneCount: 0,
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
					everyoneCount: 0,
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
				everyoneCount: 0,
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
					everyoneCount: 0,
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
				everyoneCount: 0,
			}
		})

		const wrapper = await createWrapper()
		await nextTick()

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
					everyoneCount: 0,
				}
			}

			return null
		})

		const wrapper = await createWrapper()
		await nextTick()

		expect(wrapper.text()).toContain('Signature footer')

		await wrapper.vm.onPreferenceChange('add_footer', '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"Changed template"}')

		expect(saveUserPreferenceMock).toHaveBeenCalledWith(
			'add_footer',
			'{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"Changed template","previewWidth":595,"previewHeight":100,"previewZoom":100}',
		)
	})

	it('auto-saves signing order changes without manual save button', async () => {
		const wrapper = await createWrapper()

		expect(wrapper.text()).not.toContain('Save as my default')

		await wrapper.vm.onPreferenceChange('signature_flow', 'ordered_numeric')

		expect(saveUserPreferenceMock).toHaveBeenCalledWith('signature_flow', 'ordered_numeric')
	})

	it('ignores no-op preference updates emitted during editor hydration', async () => {
		const wrapper = await createWrapper()
		await nextTick()

		saveUserPreferenceMock.mockClear()

		await wrapper.vm.onPreferenceChange('signature_flow', 'parallel')

		expect(saveUserPreferenceMock).not.toHaveBeenCalled()
	})

	it('does not autosave preference updates before initialization finishes', async () => {
		let resolveFetchEffectivePolicies: () => void = () => {}
		const pendingFetch = new Promise<void>((resolve) => {
			resolveFetchEffectivePolicies = () => {
				resolve()
			}
		})
		fetchEffectivePoliciesMock.mockImplementation(() => pendingFetch)

		const wrapper = await createWrapper()

		expect(wrapper.vm.preferencesReady).toBe(false)

		await wrapper.vm.onPreferenceChange('signature_flow', 'ordered_numeric')

		expect(saveUserPreferenceMock).not.toHaveBeenCalled()

		resolveFetchEffectivePolicies()
		await nextTick()
		await Promise.resolve()

		expect(wrapper.vm.preferencesReady).toBe(true)

		await wrapper.vm.onPreferenceChange('signature_flow', 'parallel')
		await wrapper.vm.onPreferenceChange('signature_flow', 'ordered_numeric')

		expect(saveUserPreferenceMock).toHaveBeenCalledWith('signature_flow', 'ordered_numeric')
	})

	it('ignores footer updates that only normalize to the current effective value', async () => {
		getPolicyMock.mockImplementation((key: string) => {
			if (key === 'add_footer') {
				return {
					policyKey: 'add_footer',
					effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"Inherited footer template"}',
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
					groupCount: 0,
					userCount: 0,
					everyoneCount: 0,
				}
			}

			return null
		})

		const wrapper = await createWrapper()
		await nextTick()

		saveUserPreferenceMock.mockClear()

		await wrapper.vm.onPreferenceChange('add_footer', '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"Inherited footer template","previewWidth":595,"previewHeight":100,"previewZoom":100}')

		expect(saveUserPreferenceMock).not.toHaveBeenCalled()
	})

	it('does not expose reset action after footer autosave when no persisted user default exists yet', async () => {
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
					everyoneCount: 0,
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
			'{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"Changed template","previewWidth":595,"previewHeight":100,"previewZoom":100}',
		)
		await vi.waitFor(() => {
			expect(wrapper.vm.isAutoSaveSavedFor('add_footer')).toBe(true)
		})
		expect(wrapper.vm.canUndoAutoSaveFor('add_footer')).toBe(false)

		await wrapper.vm.undoAutoSaveByKey('add_footer')

		expect(clearUserPreferenceMock).not.toHaveBeenCalled()
		expect(wrapper.vm.canUndoAutoSaveFor('add_footer')).toBe(false)
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
					everyoneCount: 0,
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
				everyoneCount: 0,
			}
		})

		const wrapper = await createWrapper()
		await nextTick()

		expect(wrapper.text()).toContain('Signature footer')
		expect(wrapper.findComponent({ name: 'SignatureFooterRuleEditor' }).exists()).toBe(false)
		expect(wrapper.text()).toContain('does not allow saving a personal default')
	})

	it('hides the merged signature stamp preference when its companion policy blocks personal defaults', async () => {
		getPolicyMock.mockImplementation((key: string) => {
			if (key === 'signature_stamp') {
				return createPolicyState({
					policyKey: key,
					effectiveValue: signatureStampPreferenceValue,
					canSaveAsUserDefault: true,
				})
			}

			if (key === 'collect_metadata') {
				return createPolicyState({
					policyKey: key,
					effectiveValue: true,
					sourceScope: 'group',
					blockedBy: 'group',
					canSaveAsUserDefault: false,
					canUseAsRequestOverride: false,
				})
			}

			return null
		})

		const wrapper = await createWrapper()
		await nextTick()

		expect(wrapper.text()).not.toContain('Signature stamp text')
		expect(wrapper.text()).not.toContain('Collect signer metadata')
		expect(wrapper.vm.preferenceEntries.some((entry: { definition: { key: string } }) => entry.definition.key === 'signature_stamp')).toBe(false)
		expect(wrapper.findComponent({ name: 'SignatureTextRuleEditor' }).exists()).toBe(false)
	})

	it('saves collect metadata changes through the merged signature stamp preference', async () => {
		getPolicyMock.mockImplementation((key: string) => {
			if (key === 'signature_stamp') {
				return createPolicyState({
					policyKey: key,
					effectiveValue: signatureStampPreferenceValue,
				})
			}

			if (key === 'collect_metadata') {
				return createPolicyState({
					policyKey: key,
					effectiveValue: true,
					allowedValues: [],
				})
			}

			return null
		})

		const wrapper = await createWrapper()
		await nextTick()
		saveUserPreferenceMock.mockClear()

		await wrapper.vm.onPreferenceChange('signature_stamp', {
			signatureStampValue: signatureStampEditorValue,
			collectMetadataEnabled: false,
		})

		expect(saveUserPreferenceMock).toHaveBeenCalledTimes(1)
		expect(saveUserPreferenceMock).toHaveBeenCalledWith('collect_metadata', false)
	})

	it('saves signature stamp and collect metadata when both merged values change', async () => {
		getPolicyMock.mockImplementation((key: string) => {
			if (key === 'signature_stamp') {
				return createPolicyState({
					policyKey: key,
					effectiveValue: signatureStampPreferenceValue,
				})
			}

			if (key === 'collect_metadata') {
				return createPolicyState({
					policyKey: key,
					effectiveValue: true,
					allowedValues: [],
				})
			}

			return null
		})

		const wrapper = await createWrapper()
		await nextTick()
		saveUserPreferenceMock.mockClear()

		const changedSignatureStampValue = JSON.stringify({
			template: 'Signed by {{SignerCommonName}}\nCustom footer',
			template_font_size: 9.8,
			signature_font_size: 20,
			signature_width: 350,
			signature_height: 100,
			background_type: 'default',
			render_mode: 'default',
		})

		await wrapper.vm.onPreferenceChange('signature_stamp', {
			signatureStampValue: changedSignatureStampValue,
			collectMetadataEnabled: false,
		})

		expect(saveUserPreferenceMock).toHaveBeenCalledTimes(2)
		expect(saveUserPreferenceMock).toHaveBeenNthCalledWith(1, 'signature_stamp', changedSignatureStampValue)
		expect(saveUserPreferenceMock).toHaveBeenNthCalledWith(2, 'collect_metadata', false)
	})

	it('shows reset button on page load when user already has a saved preference', async () => {
		getPolicyMock.mockImplementation((key: string) => {
			if (key === 'signature_flow') {
				return {
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
					everyoneCount: 0,
				}
			}
			return null
		})

		const wrapper = await createWrapper()
		await nextTick()

		// Reset button must be visible even without any in-session change
		expect(wrapper.vm.canUndoAutoSaveFor('signature_flow')).toBe(true)
		expect(wrapper.vm.undoLabelFor('signature_flow')).toBe('Reset to default')
	})

	it('uses reset-to-default label and only shows reset when a saved preference exists', async () => {
		const wrapper = await createWrapper()
		await nextTick()

		// Initially no saved preference → button hidden
		expect(wrapper.vm.canUndoAutoSaveFor('signature_flow')).toBe(false)
		expect(wrapper.vm.undoLabelFor('signature_flow')).toBe('Reset to default')

		// Changing value does not create temporary undo state anymore
		wrapper.vm.onPreferenceChange('signature_flow', 'ordered_numeric')
		expect(wrapper.vm.canUndoAutoSaveFor('signature_flow')).toBe(false)
		expect(wrapper.vm.undoLabelFor('signature_flow')).toBe('Reset to default')
	})
	it('shows reset button for add_footer on page load when user has a saved preference', async () => {
		getPolicyMock.mockImplementation((key: string) => {
			if (key === 'add_footer') {
				return {
					policyKey: 'add_footer',
					effectiveValue: '{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":true,"footerTemplate":"My custom template"}',
					sourceScope: 'user',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
					groupCount: 0,
					userCount: 0,
					everyoneCount: 0,
				}
			}
			return null
		})

		const wrapper = await createWrapper()
		await nextTick()

		expect(wrapper.vm.canUndoAutoSaveFor('add_footer')).toBe(true)
		expect(wrapper.vm.undoLabelFor('add_footer')).toBe('Reset to default')
	})
})
