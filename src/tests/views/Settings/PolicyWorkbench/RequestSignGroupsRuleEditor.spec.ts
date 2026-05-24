/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../testHelpers/l10n.js'

vi.mock('@nextcloud/l10n', async () => {
	const { createL10nMock } = await import('../../../testHelpers/l10n.js')
	return createL10nMock()
})

const { currentUserState, initialConfigState, axiosGet } = vi.hoisted(() => ({
	currentUserState: {
		isAdmin: true,
	},
	initialConfigState: {
		manageable_policy_group_ids: [] as string[],
	},
	axiosGet: vi.fn(),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => currentUserState),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, key: string, defaultValue: unknown) => {
		if (key === 'config') {
			return initialConfigState
		}

		return defaultValue
	}),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: axiosGet,
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

import RequestSignGroupsRuleEditor from '../../../../views/Settings/PolicyWorkbench/settings/request-sign-groups/RequestSignGroupsRuleEditor.vue'

const NcSelectStub = {
	name: 'NcSelect',
	props: ['options'],
	template: '<div class="nc-select-stub">{{ JSON.stringify(options) }}</div>',
}

function mountEditor(modelValue = '["finance"]') {
	return mount(RequestSignGroupsRuleEditor, {
		props: {
			modelValue,
			editorScope: 'system',
			editorMode: 'edit',
			editorTargetIds: [],
		},
		global: {
			stubs: {
				NcSelect: NcSelectStub,
			},
		},
	})
}

function mountEditorWithScopeState(modelValue = '[]', hasSelectedTargets = false) {
	return mount(RequestSignGroupsRuleEditor, {
		props: {
			modelValue,
			editorScope: 'group',
			editorMode: 'create',
			hasSelectedTargets,
		},
		global: {
			stubs: {
				NcSelect: NcSelectStub,
			},
		},
	})
}

function mountEditorWithProps(modelValue: string, props: Record<string, unknown>) {
	return mount(RequestSignGroupsRuleEditor, {
		props: {
			modelValue,
			...props,
		},
		global: {
			stubs: {
				NcSelect: {
					props: ['options'],
					template: '<div class="nc-select-stub">{{ JSON.stringify(options) }}</div>',
				},
			},
		},
	})
}

describe('RequestSignGroupsRuleEditor.vue', () => {
	it('loads groups from cloud/groups/details for instance admin', async () => {
		currentUserState.isAdmin = true
		initialConfigState.manageable_policy_group_ids = []
		axiosGet.mockReset()
		axiosGet.mockResolvedValue({
			data: {
				ocs: {
					data: {
						groups: [
							{ id: 'finance', displayname: 'Finance' },
							{ id: 'legal', displayname: 'Legal' },
						],
					},
				},
			},
		})

		const wrapper = mountEditor()
		await Promise.resolve()
		await Promise.resolve()

		expect(axiosGet).toHaveBeenCalledWith('cloud/groups/details', {
			params: {
				search: '',
				limit: 40,
				offset: 0,
			},
		})
		expect(wrapper.text()).toContain('Finance')
		expect(wrapper.text()).toContain('Legal')
	})

	it('filters available groups to manageable scope when user manages exactly one group', async () => {
		currentUserState.isAdmin = false
		initialConfigState.manageable_policy_group_ids = ['finance']
		axiosGet.mockReset()
		axiosGet.mockResolvedValue({
			data: {
				ocs: {
					data: {
						groups: ['finance', 'legal'],
					},
				},
			},
		})

		const wrapper = mountEditor('["finance","legal"]')
		await Promise.resolve()
		await Promise.resolve()

		expect(axiosGet).toHaveBeenCalledWith('cloud/groups', {
			params: {
				search: '',
				limit: 40,
				offset: 0,
			},
		})
		expect(wrapper.text()).toContain('finance')
		expect(wrapper.text()).not.toContain('legal')
	})

	it('filters available groups to manageable scope when user manages multiple groups', async () => {
		currentUserState.isAdmin = false
		initialConfigState.manageable_policy_group_ids = ['finance', 'hr']
		axiosGet.mockReset()
		axiosGet.mockResolvedValue({
			data: {
				ocs: {
					data: {
						groups: ['finance', 'hr', 'legal'],
					},
				},
			},
		})

		const wrapper = mountEditor('[]')
		await Promise.resolve()
		await Promise.resolve()

		expect(wrapper.text()).toContain('finance')
		expect(wrapper.text()).toContain('hr')
		expect(wrapper.text()).not.toContain('legal')
	})

	it('does not filter available groups for instance admin even when manageable_policy_group_ids is set', async () => {
		currentUserState.isAdmin = true
		initialConfigState.manageable_policy_group_ids = ['finance']
		axiosGet.mockReset()
		axiosGet.mockResolvedValue({
			data: {
				ocs: {
					data: {
						groups: [
							{ id: 'finance', displayname: 'Finance' },
							{ id: 'legal', displayname: 'Legal' },
						],
					},
				},
			},
		})

		const wrapper = mountEditor('[]')
		await Promise.resolve()
		await Promise.resolve()

		expect(wrapper.text()).toContain('Finance')
		expect(wrapper.text()).toContain('Legal')
	})

	it('renders delegated authorization copy', async () => {
		currentUserState.isAdmin = true
		initialConfigState.manageable_policy_group_ids = []
		axiosGet.mockReset()
		axiosGet.mockResolvedValue({
			data: {
				ocs: {
					data: {
						groups: [
							{ id: 'admin', displayname: 'Admin' },
						],
					},
				},
			},
		})

		const wrapper = mountEditor('["finance"]')
		await Promise.resolve()
		await Promise.resolve()

		expect(wrapper.text()).toContain('Authorized requester groups')
		expect(wrapper.text()).toContain('Choose which groups may create signature requests within this scope.')
		expect(wrapper.text()).toContain('Only groups you belong to may be authorized.')
	})

	it('keeps long group labels visible for overflow-sensitive cases', async () => {
		currentUserState.isAdmin = true
		initialConfigState.manageable_policy_group_ids = []
		axiosGet.mockReset()
		axiosGet.mockResolvedValue({
			data: {
				ocs: {
					data: {
						groups: [
							{
								id: 'regional-operations-and-compliance-supervision',
								displayname: 'Regional Operations and Compliance Supervision Team',
							},
						],
					},
				},
			},
		})

		const wrapper = mountEditor('[]')
		await Promise.resolve()
		await Promise.resolve()

		expect(wrapper.text()).toContain('Regional Operations and Compliance Supervision Team')
	})

	it('shows setup hint instead of requester selector until scope groups are selected in group create flow', async () => {
		currentUserState.isAdmin = true
		initialConfigState.manageable_policy_group_ids = []
		axiosGet.mockReset()
		axiosGet.mockResolvedValue({
			data: {
				ocs: {
					data: {
						groups: [
							{ id: 'board', displayname: 'Board' },
						],
					},
				},
			},
		})

		const wrapperWithoutTargets = mountEditorWithScopeState('[]', false)
		await Promise.resolve()
		await Promise.resolve()

		expect(wrapperWithoutTargets.text()).toContain('Select scope groups first to define authorized requester groups.')
		expect(wrapperWithoutTargets.text()).not.toContain('Authorized requester groups')

		const wrapperWithTargets = mountEditorWithScopeState('["board"]', true)
		await Promise.resolve()
		await Promise.resolve()

		expect(wrapperWithTargets.text()).toContain('Authorized requester groups')
	})

	it('re-adds managed target group when group admin tries to remove it in edit mode', async () => {
		currentUserState.isAdmin = false
		initialConfigState.manageable_policy_group_ids = ['board', 'company']
		axiosGet.mockReset()
		axiosGet.mockResolvedValue({
			data: {
				ocs: {
					data: {
						groups: ['board', 'company'],
					},
				},
			},
		})

		const wrapper = mountEditorWithProps('["board","company"]', {
			editorScope: 'group',
			editorMode: 'edit',
			editorTargetIds: ['board'],
		})
		await Promise.resolve()
		await Promise.resolve()

		const select = wrapper.findComponent(NcSelectStub)
		select.vm.$emit('update:modelValue', ['company'])

		const updateEvents = wrapper.emitted('update:modelValue')
		expect(updateEvents).toBeTruthy()
		expect(updateEvents?.at(-1)?.[0]).toBe('["board","company"]')
		expect(wrapper.text()).toContain('Your managed group must remain authorized in this rule.')
	})

	it('does not force target group for instance admin in edit mode', async () => {
		currentUserState.isAdmin = true
		initialConfigState.manageable_policy_group_ids = ['board']
		axiosGet.mockReset()
		axiosGet.mockResolvedValue({
			data: {
				ocs: {
					data: {
						groups: [
							{ id: 'board', displayname: 'Board' },
							{ id: 'company', displayname: 'Company' },
						],
					},
				},
			},
		})

		const wrapper = mountEditorWithProps('["board","company"]', {
			editorScope: 'group',
			editorMode: 'edit',
			editorTargetIds: ['board'],
		})
		await Promise.resolve()
		await Promise.resolve()

		const select = wrapper.findComponent(NcSelectStub)
		select.vm.$emit('update:modelValue', ['company'])

		const updateEvents = wrapper.emitted('update:modelValue')
		expect(updateEvents).toBeTruthy()
		expect(updateEvents?.at(-1)?.[0]).toBe('["company"]')
		expect(wrapper.text()).not.toContain('Your managed group must remain authorized in this rule.')
	})
})
