/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import SignerRow from '../../../../../components/Request/SignDetail/partials/SignerRow.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
}))

vi.mock('@nextcloud/moment', () => ({
	default: vi.fn(() => ({
		toDate: () => new Date('2026-01-01T00:00:00Z'),
	})),
}))

describe('SignerRow.vue', () => {
	it('renders named actions slot content', () => {
		const wrapper = mount(SignerRow, {
			props: {
				signer: {
					signRequestId: 10,
					displayName: 'John Doe',
					email: 'john@example.com',
					description: null,
					request_sign_date: '2026-01-01',
					signed: null,
					status: 1,
					statusText: 'Able to sign',
					me: false,
					visibleElements: [],
				},
				elementId: 10,
			},
			slots: {
				actions: '<button class="slot-action">Act</button>',
			},
			global: {
				stubs: {
					NcAvatar: { template: '<div class="avatar-stub" />' },
					NcListItem: {
						template: '<div><slot name="icon" /><slot name="subname" /><slot name="actions" /></div>',
					},
				},
			},
		})

		expect(wrapper.find('.slot-action').exists()).toBe(true)
		expect(wrapper.text()).toContain('pending')
	})
})
