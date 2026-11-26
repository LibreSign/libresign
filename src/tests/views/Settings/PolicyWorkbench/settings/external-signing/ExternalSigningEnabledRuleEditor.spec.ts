/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createExternalSigningAdminOcsAdapter } from '../../../../../../services/externalSigningAdminOcs'
import ExternalSigningEnabledRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/external-signing/ExternalSigningEnabledRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())
vi.mock('../../../../../../views/Settings/ExternalSigning/ExternalSigningAdmin.vue', () => ({
	default: { name: 'ExternalSigningAdmin', props: ['adapter'], template: '<div class="scoped-admin" />' },
}))
vi.mock('../../../../../../services/externalSigningAdminOcs', () => ({
	createExternalSigningAdminOcsAdapter: vi.fn(() => ({ list: vi.fn() })),
}))

function mountEditor(overrides: Record<string, unknown> = {}) {
	return mount(ExternalSigningEnabledRuleEditor, {
		props: {
			modelValue: true,
			editorScope: 'system',
			editorMode: 'edit',
			scopeTargetIds: [],
			...overrides,
		},
		global: {
			stubs: {
				NcCheckboxRadioSwitch: { template: '<div><slot /></div>' },
				ExternalSigningAdmin: { template: '<div class="scoped-admin" />', props: ['adapter'] },
			},
		},
	})
}

beforeEach(() => vi.clearAllMocks())

describe('CSC scoped rule editor', () => {
	it('configures a connection for the system rule only after the rule is saved', () => {
		const create = mountEditor({ editorMode: 'create' })
		expect(create.find('.scoped-admin').exists()).toBe(false)
		expect(create.text()).toContain('Save the policy rule first')
		expect(createExternalSigningAdminOcsAdapter).not.toHaveBeenCalled()

		const edit = mountEditor()
		expect(edit.find('.scoped-admin').exists()).toBe(true)
		expect(createExternalSigningAdminOcsAdapter).toHaveBeenCalledWith({ scopeType: 'system', scopeId: '' })
	})

	it('configures only the chosen group rule, never a global connection', () => {
		const editor = mountEditor({ editorScope: 'group', scopeTargetIds: ['client-a'] })
		expect(editor.find('.scoped-admin').exists()).toBe(true)
		expect(createExternalSigningAdminOcsAdapter).toHaveBeenCalledWith({ scopeType: 'group', scopeId: 'client-a' })
	})

	it('supports personal user rules and blocks ambiguous multi-target rules', () => {
		const personal = mountEditor({ editorScope: 'user', scopeTargetIds: ['alice'] })
		expect(personal.find('.scoped-admin').exists()).toBe(true)
		expect(createExternalSigningAdminOcsAdapter).toHaveBeenCalledWith({ scopeType: 'user', scopeId: 'alice' })

		vi.mocked(createExternalSigningAdminOcsAdapter).mockClear()
		const multi = mountEditor({ editorScope: 'group', scopeTargetIds: ['client-a', 'client-b'] })
		expect(multi.find('.scoped-admin').exists()).toBe(false)
		expect(multi.text()).toContain('Edit one group or account rule at a time')
		expect(createExternalSigningAdminOcsAdapter).not.toHaveBeenCalled()
	})
})
