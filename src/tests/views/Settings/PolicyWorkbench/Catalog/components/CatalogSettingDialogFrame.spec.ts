/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import CatalogSettingDialogFrame from '../../../../../../views/Settings/PolicyWorkbench/Catalog/components/CatalogSettingDialogFrame.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

describe('CatalogSettingDialogFrame.vue', () => {
	it('renders default inline summary and emits change-default when requested', async () => {
		const wrapper = mount(CatalogSettingDialogFrame, {
			props: {
				dialogDescription: 'Configure how signing works for this setting.',
				priorityNoteScopes: ['Account', 'Group', 'Default'],
				removalFeedback: 'Rule removed successfully.',
				showDefaultInline: true,
				defaultInlineLabel: 'Default:',
				currentBaseValue: 'Sequential',
				defaultSourceLabel: 'custom',
				showChangeDefaultAction: true,
			},
			global: {
				stubs: {
					NcNoteCard: { template: '<div class="note-card-stub"><slot /></div>' },
					NcButton: {
						emits: ['click'],
						template: '<button :class="$attrs.class" @click="$emit(\'click\', $event)"><slot /></button>',
					},
					PolicyPrecedenceHint: { template: '<div class="precedence-hint-stub"><slot /></div>' },
				},
			},
			slots: {
				default: '<div class="crud-table-slot">Rules table slot</div>',
			},
		})

		expect(wrapper.text()).toContain('Configure how signing works for this setting.')
		expect(wrapper.text()).toContain('Rule removed successfully.')
		expect(wrapper.text()).toContain('Default:')
		expect(wrapper.text()).toContain('Sequential')
		expect(wrapper.text()).toContain('(custom)')
		expect(wrapper.find('.crud-table-slot').exists()).toBe(true)

		await wrapper.find('.policy-workbench__default-inline-action').trigger('click')
		expect(wrapper.emitted('change-default')).toHaveLength(1)
	})

	it('hides the change action when the current actor cannot edit the default', () => {
		const wrapper = mount(CatalogSettingDialogFrame, {
			props: {
				dialogDescription: 'Read only description',
				priorityNoteScopes: ['Account', 'Group'],
				removalFeedback: null,
				showDefaultInline: true,
				defaultInlineLabel: 'Default access:',
				currentBaseValue: 'Not configured',
				defaultSourceLabel: 'default',
				showChangeDefaultAction: false,
			},
			global: {
				stubs: {
					NcNoteCard: { template: '<div><slot /></div>' },
					NcButton: { template: '<button><slot /></button>' },
					PolicyPrecedenceHint: { template: '<div class="precedence-hint-stub" />' },
				},
			},
		})

		expect(wrapper.find('.policy-workbench__default-inline-action').exists()).toBe(false)
	})
})
