/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../testHelpers/l10n.js'
import PolicyCatalog from '../../../views/Settings/SignatureFlowPolicy/PolicyCatalog.vue'

const fetchEffectivePolicies = vi.fn()
const getPolicy = vi.fn()

vi.mock('@nextcloud/l10n', () => createL10nMock())
vi.mock('../../../store/policies', () => ({
	usePoliciesStore: () => ({
		fetchEffectivePolicies,
		getPolicy,
	}),
}))

describe('PolicyCatalog.vue', () => {
	beforeEach(() => {
		fetchEffectivePolicies.mockReset()
		getPolicy.mockReset()
	})

	it('renders the catalog and exposes signing order as the live item', () => {
		getPolicy.mockReturnValue({ effectiveValue: 'ordered_numeric' })

		const wrapper = mount(PolicyCatalog, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<section><slot /></section>' },
					NcNoteCard: { template: '<div><slot /></div>' },
					SignatureFlow: { template: '<div class="signature-flow-stub">Signing order editor</div>' },
				},
			},
		})

		expect(wrapper.text()).toContain('One list, live settings')
		expect(wrapper.text()).toContain('Unified settings catalog')
		expect(wrapper.text()).toContain('signature_flow')
		expect(wrapper.text()).toContain('Signing order')
		expect(wrapper.text()).toContain('Current effective value: Sequential.')
		expect(wrapper.text()).toContain('signature_stamp')
		expect(wrapper.find('.signature-flow-stub').exists()).toBe(true)
		expect(fetchEffectivePolicies).not.toHaveBeenCalled()
	})

	it('loads the effective policies when signing order state is not bootstrapped yet', async () => {
		getPolicy.mockReturnValue(null)

		mount(PolicyCatalog, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<section><slot /></section>' },
					NcNoteCard: { template: '<div><slot /></div>' },
					SignatureFlow: true,
				},
			},
		})

		await Promise.resolve()

		expect(fetchEffectivePolicies).toHaveBeenCalledTimes(1)
	})
})
