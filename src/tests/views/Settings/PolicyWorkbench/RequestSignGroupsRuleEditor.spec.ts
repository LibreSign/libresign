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

function mountEditor(modelValue = '["finance"]') {
	return mount(RequestSignGroupsRuleEditor, {
		props: {
			modelValue,
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
})
