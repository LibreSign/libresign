/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import type { EffectivePolicyState } from '../../../../../../types/index'
import { signatureRejectionPersonalPreferenceBehavior as behavior } from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-rejection/personalPreferenceBehavior'
import type { RealPolicyPersonalPreferenceContext } from '../../../../../../views/Settings/PolicyWorkbench/settings/realTypes'

const saveUserPreferenceCompound = vi.hoisted(() => vi.fn())
const fetchEffectivePolicies = vi.hoisted(() => vi.fn())

vi.mock('@nextcloud/l10n', () => createL10nMock())
vi.mock('../../../../../../store/policies', () => ({
	usePoliciesStore: () => ({ saveUserPreferenceCompound, fetchEffectivePolicies }),
}))
vi.mock('../../../../../../views/Preferences/personalPreferenceVisibility', () => ({
	canRenderPersonalPreferencePolicy: (_key: string, policy: EffectivePolicyState | null) => policy?.canSaveAsUserDefault === true || policy?.sourceScope === 'user',
}))

const effectiveValues: Record<string, unknown> = {
	rejection_enabled: true,
	rejection_behavior: 'continue',
	rejection_comment_mode: 'required',
	rejection_visibility: 'participants',
	rejection_comment_visibility: 'requester',
}

const editorValue = {
	enabled: true,
	behavior: 'continue',
	commentMode: 'required',
	visibility: 'participants',
	commentVisibility: 'requester',
}

function createContext(overrides: Record<string, Partial<EffectivePolicyState> | null | undefined> = {}): RealPolicyPersonalPreferenceContext {
	return {
		getPolicy: (key: string) => {
			if (overrides[key] === null) {
				return null
			}

			return {
				policyKey: key,
				effectiveValue: effectiveValues[key],
				sourceScope: 'global',
				canSaveAsUserDefault: true,
				...overrides[key],
			} as EffectivePolicyState
		},
		saveUserPreference: vi.fn().mockResolvedValue(undefined),
		clearUserPreference: vi.fn().mockResolvedValue(undefined),
	}
}

describe('signatureRejectionPersonalPreferenceBehavior', () => {
	beforeEach(() => {
		saveUserPreferenceCompound.mockReset().mockResolvedValue(null)
		fetchEffectivePolicies.mockReset().mockResolvedValue(undefined)
	})

	it('selects and compares the five effective values, not only the parent', () => {
		const context = createContext()

		expect(behavior.resolveSelectedValue!(null, context)).toEqual(editorValue)
		expect(behavior.getEffectiveValue!(null, context)).toEqual(editorValue)
	})

	it('saves the five values at once and reloads them', async () => {
		const context = createContext()

		await behavior.savePreference!(editorValue, context)

		expect(saveUserPreferenceCompound).toHaveBeenCalledWith('rejection_enabled', effectiveValues)
		expect(context.saveUserPreference).not.toHaveBeenCalled()
		expect(fetchEffectivePolicies).toHaveBeenCalledOnce()
	})

	it('does not reload when the backend refuses the save', async () => {
		saveUserPreferenceCompound.mockRejectedValue(new Error('refused'))

		await expect(behavior.savePreference!(editorValue, createContext())).rejects.toThrow('refused')
		expect(fetchEffectivePolicies).not.toHaveBeenCalled()
	})

	it('clears the preference of every key', async () => {
		const context = createContext()

		await behavior.clearPreference!(context)

		expect(vi.mocked(context.clearUserPreference).mock.calls.map(([key]) => key)).toEqual(Object.keys(effectiveValues))
	})

	it.each([
		{ name: 'every key can be saved', overrides: {}, canSave: true },
		{ name: 'the parent is locked', overrides: { rejection_enabled: { canSaveAsUserDefault: false } }, canSave: false },
		{ name: 'one dependent key is locked', overrides: { rejection_visibility: { canSaveAsUserDefault: false } }, canSave: false },
		{ name: 'one dependent key is missing', overrides: { rejection_behavior: null }, canSave: false },
	])('resolves whether it can save when $name', ({ overrides, canSave }) => {
		const context = createContext(overrides)

		expect(behavior.canSave!(null, context)).toBe(canSave)
		expect(behavior.resolvePolicy!(null, context)?.canSaveAsUserDefault).toBe(canSave)
	})

	it.each([
		{ name: 'no key has a preference', overrides: {}, saved: false, sourceScope: 'global' },
		{ name: 'the parent has a preference', overrides: { rejection_enabled: { sourceScope: 'user' } }, saved: true, sourceScope: 'user' },
		{ name: 'only a dependent key has a preference', overrides: { rejection_comment_mode: { sourceScope: 'user' } }, saved: true, sourceScope: 'user' },
	])('reports the saved preference when $name', ({ overrides, saved, sourceScope }) => {
		const context = createContext(overrides)

		expect(behavior.hasSavedPreference!(null, context)).toBe(saved)
		expect(behavior.resolvePolicy!(null, context)?.sourceScope).toBe(sourceScope)
	})

	it.each([
		{ name: 'no key', overrides: {}, cleared: false },
		{ name: 'the parent', overrides: { rejection_enabled: { preferenceWasCleared: true } }, cleared: true },
		{ name: 'a dependent key', overrides: { rejection_comment_mode: { preferenceWasCleared: true } }, cleared: true },
	])('reports a cleared preference when it was cleared on $name', ({ overrides, cleared }) => {
		expect(behavior.resolvePolicy!(null, createContext(overrides))?.preferenceWasCleared).toBe(cleared)
	})

	it.each([
		{ name: 'renders while the five keys can be saved', overrides: {}, rendered: true },
		{ name: 'hides while a key is locked and nothing was saved', overrides: { rejection_behavior: { canSaveAsUserDefault: false } }, rendered: false },
		{ name: 'keeps a saved preference visible', overrides: { rejection_enabled: { canSaveAsUserDefault: false, sourceScope: 'user' } }, rendered: true },
		{ name: 'hides without the parent policy', overrides: { rejection_enabled: null }, rendered: false },
	])('$name', ({ overrides, rendered }) => {
		expect(behavior.shouldRender!(null, createContext(overrides))).toBe(rendered)
	})
})
