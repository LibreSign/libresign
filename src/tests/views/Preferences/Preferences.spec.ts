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
const getPolicyMock = vi.fn<() => EffectivePolicyState | null>()

vi.mock('@nextcloud/l10n', () => createL10nMock())

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

describe('Preferences view', () => {
	beforeEach(() => {
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
				},
			},
		})
	}

	it('loads effective policies on mount', async () => {
		await createWrapper()

		expect(fetchEffectivePoliciesMock).toHaveBeenCalledTimes(1)
	})

	it('shows the effective signing order summary', async () => {
		const wrapper = await createWrapper()

		expect(wrapper.text()).toContain('Effective signing order')
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
			sourceScope: 'group',
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
})
