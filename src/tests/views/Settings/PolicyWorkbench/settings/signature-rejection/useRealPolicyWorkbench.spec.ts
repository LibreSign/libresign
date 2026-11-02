/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

import {
	clearGroupPolicy,
	fetchEffectivePolicies,
	fetchGroupPolicy,
	fetchSystemPolicy,
	getPolicy,
	resetWorkbenchHarness,
	saveGroupPolicyCompound,
	saveSystemPolicyCompound,
} from '../workbenchTestUtils'
import { createRealPolicyWorkbenchState } from '../../../../../../views/Settings/PolicyWorkbench/useRealPolicyWorkbench'

const PARENT_KEY = 'rejection_enabled'
const HELPER_KEYS = [
	'rejection_behavior',
	'rejection_comment_mode',
	'rejection_visibility',
	'rejection_comment_visibility',
]
const ALL_KEYS = [PARENT_KEY, ...HELPER_KEYS]

const configured = {
	enabled: true,
	behavior: 'continue',
	commentMode: 'required',
	visibility: 'participants',
	commentVisibility: 'requester',
}

const defaults = {
	enabled: false,
	behavior: 'cancel',
	commentMode: 'disabled',
	visibility: 'requester',
	commentVisibility: 'requester',
}

const configuredKeys = {
	rejection_enabled: true,
	rejection_behavior: 'continue',
	rejection_comment_mode: 'required',
	rejection_visibility: 'participants',
	rejection_comment_visibility: 'requester',
}

/** Persisted layers, written by the compound save mocks and read back on hydration. */
let systemLayer: Record<string, unknown>
let groupLayers: Record<string, Record<string, unknown>>

function mockPolicyStates(): void {
	getPolicy.mockImplementation((key: string) => {
		if (key === PARENT_KEY) {
			const hasGlobalRule = PARENT_KEY in systemLayer
			return {
				effectiveValue: hasGlobalRule ? systemLayer[PARENT_KEY] : false,
				sourceScope: hasGlobalRule ? 'global' : 'system',
				editableByCurrentActor: true,
				allowedValues: [false, true],
				meta: { compositeChildren: HELPER_KEYS },
			}
		}

		return { effectiveValue: null, sourceScope: 'system', editableByCurrentActor: true }
	})
}

function mockPersistence(): void {
	saveSystemPolicyCompound.mockImplementation(async (_parentKey: string, values: Record<string, unknown>) => {
		Object.assign(systemLayer, values)
		return null
	})
	saveGroupPolicyCompound.mockImplementation(async (groupId: string, _parentKey: string, values: Record<string, unknown>) => {
		groupLayers[groupId] = { ...groupLayers[groupId], ...values }
		return null
	})
	clearGroupPolicy.mockImplementation(async (groupId: string, policyKey: string) => {
		delete groupLayers[groupId]?.[policyKey]
		return null
	})
	fetchSystemPolicy.mockImplementation(async (policyKey: string) => {
		if (!(policyKey in systemLayer)) {
			return null
		}

		return { policyKey, scope: 'global', value: systemLayer[policyKey], allowChildOverride: true, visibleToChild: true, allowedValues: [] }
	})
	fetchGroupPolicy.mockImplementation(async (groupId: string, policyKey: string) => {
		const layer = groupLayers[groupId]
		if (!layer || !(policyKey in layer)) {
			return null
		}

		return { policyKey, scope: 'group', targetId: groupId, value: layer[policyKey], allowChildOverride: true, visibleToChild: true, allowedValues: [] }
	})
}

describe('signature rejection workbench', () => {
	beforeEach(() => {
		resetWorkbenchHarness()
		systemLayer = {}
		groupLayers = {}
		mockPolicyStates()
		mockPersistence()
	})

	it('lists signature rejection once and keeps the helper policies out of the catalog', () => {
		const state = createRealPolicyWorkbenchState()
		const keys = state.visibleSettingSummaries.map((summary) => summary.key)

		expect(keys.filter((key) => key === PARENT_KEY)).toHaveLength(1)
		for (const helperKey of HELPER_KEYS) {
			expect(keys).not.toContain(helperKey)
		}
	})

	it('saves a system rule with the five keys and restores them after reload', async () => {
		const editing = createRealPolicyWorkbenchState()
		editing.openSetting(PARENT_KEY)
		editing.startEditor({ scope: 'system' })
		editing.updateDraftValue(configured as never)
		await editing.saveDraft()

		expect(saveSystemPolicyCompound).toHaveBeenCalledWith(
			PARENT_KEY,
			configuredKeys,
			Object.fromEntries(ALL_KEYS.map((key) => [key, true])),
		)
		expect(editing.duplicateMessage).toBeFalsy()

		const reloaded = createRealPolicyWorkbenchState()
		reloaded.openSetting(PARENT_KEY)

		await vi.waitFor(() => {
			expect(reloaded.inheritedSystemRule?.value).toEqual(configured)
		})
		for (const key of ALL_KEYS) {
			expect(fetchSystemPolicy).toHaveBeenCalledWith(key)
		}
	})

	it('restores the options prepared while rejection is disabled', async () => {
		const prepared = { ...configured, enabled: false }
		const editing = createRealPolicyWorkbenchState()
		editing.openSetting(PARENT_KEY)
		editing.startEditor({ scope: 'system' })
		editing.updateDraftValue(prepared as never)
		await editing.saveDraft()

		const reloaded = createRealPolicyWorkbenchState()
		reloaded.openSetting(PARENT_KEY)

		await vi.waitFor(() => {
			expect(reloaded.inheritedSystemRule?.value).toEqual(prepared)
		})
	})

	it('saves, reloads and removes a group rule through the same generic flow', async () => {
		const editing = createRealPolicyWorkbenchState()
		editing.openSetting(PARENT_KEY)
		editing.startEditor({ scope: 'group' })
		editing.updateDraftTargets(['finance'])
		editing.updateDraftValue(configured as never)
		await editing.saveDraft()

		expect(saveGroupPolicyCompound).toHaveBeenCalledWith(
			'finance',
			PARENT_KEY,
			configuredKeys,
			Object.fromEntries(ALL_KEYS.map((key) => [key, true])),
		)

		const reloaded = createRealPolicyWorkbenchState()
		reloaded.openSetting(PARENT_KEY)

		await vi.waitFor(() => {
			expect(reloaded.visibleGroupRules).toHaveLength(1)
		})
		expect(reloaded.visibleGroupRules[0]).toMatchObject({ targetId: 'finance', value: configured })

		await reloaded.removeRule(reloaded.visibleGroupRules[0]?.id ?? '')

		for (const key of ALL_KEYS) {
			expect(clearGroupPolicy).toHaveBeenCalledWith('finance', key)
		}
		expect(groupLayers.finance).toEqual({})
		await vi.waitFor(() => {
			expect(reloaded.visibleGroupRules).toHaveLength(0)
		})
	})

	it('shows a backend validation error instead of reporting a successful save', async () => {
		const backendError = 'A delegated rule cannot disclose a rejection to a wider audience than the parent policy allows'
		saveSystemPolicyCompound.mockRejectedValue({
			response: { data: { ocs: { data: { error: backendError } } } },
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting(PARENT_KEY)
		state.startEditor({ scope: 'system' })
		state.updateDraftValue(configured as never)
		await state.saveDraft()

		expect(state.duplicateMessage).toBe(backendError)
		expect(state.editorDraft).not.toBeNull()
		expect(state.inheritedSystemRule).toMatchObject({ id: 'system-inherited-default', value: defaults })
		expect(fetchEffectivePolicies).not.toHaveBeenCalled()
	})
})
