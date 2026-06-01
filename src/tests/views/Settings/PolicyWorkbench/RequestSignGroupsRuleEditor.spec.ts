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
	props: ['options', 'ariaLabelCombobox'],
	template: '<div class="nc-select-stub" :data-aria-label="ariaLabelCombobox">{{ JSON.stringify(options) }}</div>',
}

function mountEditor(modelValue = '["finance"]') {
	return mount(RequestSignGroupsRuleEditor, {
		props: {
			modelValue,
			editorScope: 'system',
			editorMode: 'edit',
			editorInitialTargetIds: [],
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
			editorInitialTargetIds: [],
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
				NcSelect: NcSelectStub,
			},
		},
	})
}

function findSelectByLabel(wrapper: ReturnType<typeof mount>, label: string) {
	return wrapper.findAll('.nc-select-stub').find((select) => select.attributes('data-aria-label') === label)
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
		expect(wrapper.text()).toContain('Only groups you belong to may be configured in allow or deny lists.')
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

	it('shows Authorized section when group admin creates rule for their own managed group but excludes inherited group from allow options', async () => {
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

		const wrapper = mountEditorWithProps('{"allowGroups":["board"],"denyGroups":[]}', {
			editorScope: 'group',
			editorMode: 'create',
			editorInitialTargetIds: [],
			editorTargetIds: [],
			hasSelectedTargets: true,
		})
		await Promise.resolve()
		await Promise.resolve()

		expect(wrapper.text()).toContain('Authorized requester groups')
		expect(wrapper.text()).toContain('Denied requester groups')
		expect(wrapper.text()).not.toContain('Your managed group must remain authorized in this rule.')

		const allowSelect = findSelectByLabel(wrapper, 'Authorized requester groups')
		const denySelect = findSelectByLabel(wrapper, 'Denied requester groups')

		expect(allowSelect).toBeTruthy()
		expect(denySelect).toBeTruthy()
		expect(allowSelect?.text()).toContain('company')
		expect(allowSelect?.text()).not.toContain('board')
		expect(denySelect?.text()).toContain('board')
		expect(denySelect?.text()).toContain('company')
	})

	it('does not reinsert inherited allowGroups when group admin changes only deny list during create', async () => {
		currentUserState.isAdmin = false
		initialConfigState.manageable_policy_group_ids = ['board']
		axiosGet.mockReset()
		axiosGet.mockResolvedValue({
			data: {
				ocs: {
					data: {
						groups: ['board'],
					},
				},
			},
		})

		const wrapper = mountEditorWithProps('{"allowGroups":["board"],"denyGroups":[]}', {
			editorScope: 'group',
			editorMode: 'create',
			editorInitialTargetIds: [],
			editorTargetIds: [],
			hasSelectedTargets: true,
		})
		await Promise.resolve()
		await Promise.resolve()

		const denySelect = wrapper.findAllComponents(NcSelectStub)
			.find((component) => component.attributes('data-aria-label') === 'Denied requester groups')
		expect(denySelect).toBeTruthy()
		denySelect?.vm.$emit('update:modelValue', [{ id: 'board', displayname: 'Board' }])

		const updateEvents = wrapper.emitted('update:modelValue')
		expect(updateEvents).toBeTruthy()
		expect(updateEvents?.at(-1)?.[0]).toBe('{"allowGroups":[],"denyGroups":["board"]}')
	})

	it('shows both Authorized and Denied when group admin creates rule for a group outside their managed scope', async () => {
		currentUserState.isAdmin = false
		initialConfigState.manageable_policy_group_ids = ['board']
		axiosGet.mockReset()
		axiosGet.mockResolvedValue({
			data: {
				ocs: {
					data: {
						groups: ['board', 'external-team'],
					},
				},
			},
		})

		const wrapper = mountEditorWithProps('{"allowGroups":[],"denyGroups":[]}', {
			editorScope: 'group',
			editorMode: 'create',
			editorInitialTargetIds: ['external-team'],
			editorTargetIds: ['external-team'],
			hasSelectedTargets: true,
		})
		await Promise.resolve()
		await Promise.resolve()

		// 'external-team' is not in manageable_policy_group_ids → hideAllowGroups = false
		expect(wrapper.text()).toContain('Authorized requester groups')
		expect(wrapper.text()).toContain('Denied requester groups')
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
			editorInitialTargetIds: ['board'],
			editorTargetIds: ['board'],
		})
		await Promise.resolve()
		await Promise.resolve()

		const select = wrapper.findComponent(NcSelectStub)
		select.vm.$emit('update:modelValue', ['company'])

		const updateEvents = wrapper.emitted('update:modelValue')
		expect(updateEvents).toBeTruthy()
		expect(updateEvents?.at(-1)?.[0]).toBe('{"allowGroups":["company"],"denyGroups":[]}')
		expect(wrapper.text()).not.toContain('Your managed group must remain authorized in this rule.')
	})

	it('keeps Authorized visible while create flow derives manageable targetIds from draft value', async () => {
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

		const wrapper = mountEditorWithProps('{"allowGroups":["board"],"denyGroups":[]}', {
			editorScope: 'group',
			editorMode: 'create',
			editorInitialTargetIds: [],
			editorTargetIds: ['board'],
			hasSelectedTargets: true,
		})
		await Promise.resolve()
		await Promise.resolve()

		expect(wrapper.text()).toContain('Authorized requester groups')
		expect(wrapper.text()).toContain('Denied requester groups')

		const allowSelect = findSelectByLabel(wrapper, 'Authorized requester groups')
		expect(allowSelect?.text()).not.toContain('board')
		expect(allowSelect?.text()).toContain('company')
	})

	it('keeps Authorized visible in delegated create flow even when manageable groups bootstrap is empty', async () => {
		currentUserState.isAdmin = false
		initialConfigState.manageable_policy_group_ids = []
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

		const wrapper = mountEditorWithProps('{"allowGroups":["board"],"denyGroups":[]}', {
			editorScope: 'group',
			editorMode: 'create',
			editorInitialTargetIds: [],
			editorTargetIds: ['board'],
			hasSelectedTargets: true,
		})
		await Promise.resolve()
		await Promise.resolve()

		expect(wrapper.text()).toContain('Authorized requester groups')
		expect(wrapper.text()).toContain('Denied requester groups')

		const allowSelect = findSelectByLabel(wrapper, 'Authorized requester groups')
		expect(allowSelect?.text()).not.toContain('board')
		expect(allowSelect?.text()).toContain('company')
	})

	it('keeps Authorized visible when scope is derived from current request-sign value and selector is hidden', async () => {
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

		const wrapper = mountEditorWithProps('{"allowGroups":["board"],"denyGroups":[]}', {
			editorScope: 'group',
			editorMode: 'create',
			editorInitialTargetIds: [],
			editorTargetIds: [],
			hasSelectedTargets: true,
		})
		await Promise.resolve()
		await Promise.resolve()

		expect(wrapper.text()).toContain('Authorized requester groups')
		expect(wrapper.text()).toContain('Denied requester groups')

		const allowSelect = findSelectByLabel(wrapper, 'Authorized requester groups')
		const denySelect = findSelectByLabel(wrapper, 'Denied requester groups')
		expect(allowSelect?.text()).not.toContain('board')
		expect(allowSelect?.text()).toContain('company')
		expect(denySelect?.text()).toContain('board')
	})
})
