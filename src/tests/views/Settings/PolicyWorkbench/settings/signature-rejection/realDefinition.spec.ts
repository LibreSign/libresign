/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import { signatureRejectionRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-rejection/realDefinition'
import type { EffectivePolicyState } from '../../../../../../types/index'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const defaults = {
	enabled: false,
	behavior: 'cancel',
	commentMode: 'disabled',
	visibility: 'requester',
	commentVisibility: 'requester',
}

describe('signatureRejectionRealDefinition', () => {
	it('is keyed by the parent policy and supports every scope', () => {
		expect(signatureRejectionRealDefinition.key).toBe('rejection_enabled')
		expect(signatureRejectionRealDefinition.supportedScopes).toEqual(['system', 'group', 'user'])
	})

	it('uses the generic compound contract for the five keys', () => {
		const compound = signatureRejectionRealDefinition.compound
		expect(compound).toBeDefined()
		expect(compound?.includeChildOnlyRules).toBeUndefined()

		const editorValue = compound!.compose({
			rejection_enabled: true,
			rejection_behavior: 'continue',
			rejection_comment_mode: 'optional',
			rejection_visibility: 'public',
			rejection_comment_visibility: 'participants',
		})

		expect(compound!.decompose(editorValue)).toEqual({
			rejection_enabled: true,
			rejection_behavior: 'continue',
			rejection_comment_mode: 'optional',
			rejection_visibility: 'public',
			rejection_comment_visibility: 'participants',
		})
	})

	it('starts a new rule from the defaults without sharing the object', () => {
		const first = signatureRejectionRealDefinition.createEmptyValue()
		const second = signatureRejectionRealDefinition.createEmptyValue()

		expect(first).toEqual(defaults)
		expect(first).not.toBe(second)
	})

	it.each([
		{ name: 'a system value that enables rejection', policyValue: true, sourceScope: 'system', expected: true },
		{ name: 'a system value that disables rejection', policyValue: false, sourceScope: 'system', expected: false },
		{ name: 'a value from another scope', policyValue: true, sourceScope: 'group', expected: false },
		{ name: 'no value', policyValue: null, sourceScope: 'system', expected: false },
	])('falls back to the parent policy value with $name', ({ policyValue, sourceScope, expected }) => {
		expect(signatureRejectionRealDefinition.getFallbackSystemDefault(policyValue, sourceScope)).toBe(expected)
	})

	it.each([
		{ name: 'enabled', effectiveValue: true, summary: 'Let the others keep signing · Required comment' },
		{ name: 'disabled', effectiveValue: false, summary: 'Disabled' },
	])('keeps the $name system value when the fallback is composed with its children', ({ effectiveValue, summary }) => {
		const composed = signatureRejectionRealDefinition.compound!.compose({
			rejection_enabled: signatureRejectionRealDefinition.getFallbackSystemDefault(effectiveValue, 'system'),
			rejection_behavior: 'continue',
			rejection_comment_mode: 'required',
			rejection_visibility: 'participants',
			rejection_comment_visibility: 'requester',
		})

		expect(composed).toMatchObject({ enabled: effectiveValue, behavior: 'continue', commentMode: 'required' })
		expect(signatureRejectionRealDefinition.summarizeValue(composed)).toBe(summary)
	})

	it('passes the allowChildOverride choice through unchanged', () => {
		expect(signatureRejectionRealDefinition.normalizeAllowChildOverride('system', true)).toBe(true)
		expect(signatureRejectionRealDefinition.normalizeAllowChildOverride('group', false)).toBe(false)
	})

	it('hands the backend allowed values of the parent policy to the editor', () => {
		const resolve = signatureRejectionRealDefinition.resolveEditorProps!

		expect(resolve({ allowedValues: [true] } as EffectivePolicyState, { base: 1 })).toEqual({ base: 1, allowedEnabledValues: [true] })
		expect(resolve({ allowedValues: [] } as unknown as EffectivePolicyState, { base: 1 })).toEqual({ base: 1 })
		expect(resolve(null, { base: 1 })).toEqual({ base: 1 })
	})

	it('summarizes through the model', () => {
		expect(signatureRejectionRealDefinition.summarizeValue(defaults)).toBe('Disabled')
		expect(signatureRejectionRealDefinition.summarizeValue({ ...defaults, enabled: true })).toBe('Cancel the signing request · No comment')
	})
})
