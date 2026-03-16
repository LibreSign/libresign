/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../testHelpers/l10n.js'
import { mount } from '@vue/test-utils'

type EnvelopeVm = {
	envelopeEnabled: boolean
}

const loadStateMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/l10n', () => createL10nMock())

let Envelope: unknown

beforeAll(async () => {
	;({ default: Envelope } = await import('../../../views/Settings/Envelope.vue'))
})

describe('Envelope', () => {
	beforeEach(() => {
		loadStateMock.mockReset()
	})

	it('uses typed backend state', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'envelope_enabled') return false
			return fallback
		})

		const wrapper = mount(Envelope as never, {
			global: {
				stubs: {
					NcSettingsSection: { template: '<div><slot /></div>' },
					NcCheckboxRadioSwitch: { template: '<div><slot /></div>' },
				},
			},
		})

		expect((wrapper.vm as unknown as EnvelopeVm).envelopeEnabled).toBe(false)
	})
})
