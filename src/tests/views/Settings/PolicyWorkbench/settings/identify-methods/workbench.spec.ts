/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it } from 'vitest'

import {
	configState,
	currentUserState,
	getPolicy,
	identifyMethodsInitialState,
	resetWorkbenchHarness,
} from '../workbenchTestUtils'
import { createRealPolicyWorkbenchState } from '../../../../../../views/Settings/PolicyWorkbench/useRealPolicyWorkbench'

describe('identify methods workbench', () => {
	beforeEach(() => {
		resetWorkbenchHarness()
	})

	it('keeps identify methods create draft populated when baseline policy value is empty', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'identify_methods') {
				return {
					effectiveValue: '[]',
					sourceScope: 'system',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system' }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('identify_methods')
		await Promise.resolve()
		await Promise.resolve()

		state.startEditor({ scope: 'group' })
		expect(state.editorDraft).not.toBeNull()
		const parsedDraftValue = JSON.parse(String(state.editorDraft?.value)) as { factors: Array<{ name?: string }> }
		expect(parsedDraftValue.factors).toHaveLength(1)
		expect(parsedDraftValue.factors[0]?.name).toBe('email')
	})

	it('restricts group-admin identify methods drafts to delegated enabled methods', async () => {
		currentUserState.isAdmin = false
		identifyMethodsInitialState.splice(0, identifyMethodsInitialState.length,
			{
				name: 'account',
				friendly_name: 'Account',
				enabled: true,
				requirement: 'required',
				signatureMethods: {
					password: {
						enabled: true,
						label: 'Certificate with password',
					},
				},
				signatureMethodEnabled: 'password',
			},
			{
				name: 'email',
				friendly_name: 'Email',
				enabled: false,
				requirement: 'optional',
				signatureMethods: {
					emailToken: {
						enabled: true,
						label: 'Email token',
					},
				},
				signatureMethodEnabled: 'emailToken',
			},
		)
		getPolicy.mockImplementation((key: string) => {
			if (key === 'identify_methods') {
				return {
					effectiveValue: JSON.stringify({
						factors: [
							{
								name: 'account',
								enabled: true,
								requirement: 'required',
								signatureMethods: {
									password: { enabled: true },
								},
								signatureMethodEnabled: 'password',
							},
							{
								name: 'email',
								enabled: false,
								requirement: 'optional',
								signatureMethods: {
									emailToken: { enabled: true },
								},
								signatureMethodEnabled: 'emailToken',
							},
						],
					}),
					sourceScope: 'system',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system' }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('identify_methods')
		await Promise.resolve()
		await Promise.resolve()

		state.startEditor({ scope: 'group' })

		expect(state.editorDraft).not.toBeNull()
		const parsedDraftValue = JSON.parse(String(state.editorDraft?.value)) as { factors: Array<{ name?: string }> }
		expect(parsedDraftValue.factors).toHaveLength(1)
		expect(parsedDraftValue.factors[0]?.name).toBe('account')
	})

	it('allows group-admin to create identify methods group rules when managing multiple groups', async () => {
		currentUserState.isAdmin = false
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
					visible: true,
					editableByCurrentActor: false,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system' }
		})

		const state = createRealPolicyWorkbenchState()
		state.setViewMode('group-admin')
		state.openSetting('identify_methods')
		await Promise.resolve()
		await Promise.resolve()

		expect(state.createGroupOverrideDisabledReason).toBeNull()

		state.startEditor({ scope: 'group' })

		expect(state.editorDraft?.scope).toBe('group')
	})

	it('keeps identify methods system create draft populated when baseline policy value is empty', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'identify_methods') {
				return {
					effectiveValue: '[]',
					sourceScope: 'system',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system' }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('identify_methods')
		await Promise.resolve()
		await Promise.resolve()

		state.startEditor({ scope: 'system' })
		expect(state.editorDraft).not.toBeNull()
		expect(state.canSaveDraft).toBe(true)
		const parsedDraftValue = JSON.parse(String(state.editorDraft?.value)) as { factors: Array<{ name?: string }> }
		expect(parsedDraftValue.factors).toHaveLength(1)
		expect(parsedDraftValue.factors[0]?.name).toBe('email')
	})
})
