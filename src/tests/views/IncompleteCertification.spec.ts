/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../testHelpers/l10n.js'
import { mount } from '@vue/test-utils'

const getCurrentUserMock = vi.fn()

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: () => getCurrentUserMock(),
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn(() => '/settings/admin/libresign'),
}))

vi.mock('@nextcloud/l10n', () => createL10nMock())

let IncompleteCertification: unknown

beforeAll(async () => {
	;({ default: IncompleteCertification } = await import('../../views/IncompleteCertification.vue'))
})

describe('IncompleteCertification', () => {
	beforeEach(() => {
		getCurrentUserMock.mockReset()
	})

	it('registers icon wrapper and exposes mdi icon path used in template', () => {
		getCurrentUserMock.mockReturnValue({ isAdmin: true })

		const wrapper = mount(IncompleteCertification as never, {
			global: {
				stubs: {
					NcButton: { template: '<button><slot /><slot name="icon" /></button>' },
					NcIconSvgWrapper: { name: 'NcIconSvgWrapper', props: ['path'], template: '<i class="icon" :data-path="path" />' },
				},
			},
		})

		expect(wrapper.findComponent({ name: 'NcIconSvgWrapper' }).exists()).toBe(true)
		expect(wrapper.find('.icon').attributes('data-path')).toBeTruthy()
	})
})
