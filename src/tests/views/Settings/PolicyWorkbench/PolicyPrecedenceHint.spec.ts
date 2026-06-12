/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import PolicyPrecedenceHint from '../../../../views/Settings/PolicyWorkbench/components/PolicyPrecedenceHint.vue'

function escapeHtml(value: string) {
	return value
		.replaceAll('&', '&amp;')
		.replaceAll('<', '&lt;')
		.replaceAll('>', '&gt;')
		.replaceAll('"', '&quot;')
		.replaceAll("'", '&#39;')
}

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n({
	t: (_app: string, message: string, params?: Record<string, unknown>) => {
		return message.replace(/{(\w+)}/g, (match, key) => {
			if (!params || !Object.prototype.hasOwnProperty.call(params, key)) {
				return match
			}

			return escapeHtml(String(params[key]))
		})
	},
}))

describe('PolicyPrecedenceHint.vue', () => {
	function mountComponent(props: InstanceType<typeof PolicyPrecedenceHint>['$props']) {
		return mount(PolicyPrecedenceHint, {
			props,
			global: {
				stubs: {
					NcIconSvgWrapper: {
						name: 'NcIconSvgWrapper',
						props: ['path', 'size'],
						template: '<span class="icon-stub" :data-path="path" />',
					},
				},
			},
		})
	}

	it('renders a dialog note for two or more scopes', () => {
		const wrapper = mountComponent({
			scopes: ['Account', 'Group'],
			variant: 'dialog',
		})

		expect(wrapper.find('.policy-workbench__table-priority-note').exists()).toBe(true)
		expect(wrapper.text()).toContain('Priority: Account > Group')
		expect(wrapper.text()).not.toContain('&gt;')
		expect(wrapper.find('.policy-workbench__priority-scopes').attributes('dir')).toBe('ltr')
		expect(wrapper.find('.icon-stub').exists()).toBe(true)
	})

	it('renders an editor hint paragraph without the dialog note chrome', () => {
		const wrapper = mountComponent({
			scopes: ['Account', 'Group'],
			variant: 'editor',
		})

		expect(wrapper.find('.policy-workbench__precedence-hint').exists()).toBe(true)
		expect(wrapper.find('.policy-workbench__table-priority-note').exists()).toBe(false)
		expect(wrapper.text()).toContain('Priority: Account > Group')
		expect(wrapper.text()).not.toContain('&gt;')
	})

	it('renders scope labels in an isolated LTR sequence for bidi safety', () => {
		const wrapper = mountComponent({
			scopes: ['الحساب', 'المجموعة'],
			variant: 'dialog',
		})

		const sequence = wrapper.find('.policy-workbench__priority-scopes')
		expect(sequence.exists()).toBe(true)
		expect(sequence.attributes('dir')).toBe('ltr')
		expect(wrapper.findAll('.policy-workbench__priority-scope').map((node) => node.text())).toEqual(['الحساب', 'المجموعة'])
		expect(wrapper.text()).toContain('الحساب > المجموعة')
	})

	it('renders nothing when fewer than two scopes are relevant', () => {
		const wrapper = mountComponent({
			scopes: ['Account'],
			variant: 'dialog',
		})

		expect(wrapper.find('.policy-workbench__table-priority-note').exists()).toBe(false)
		expect(wrapper.find('.policy-workbench__precedence-hint').exists()).toBe(false)
		expect(wrapper.text()).toBe('')
	})
})
