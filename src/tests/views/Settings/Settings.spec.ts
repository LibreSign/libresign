/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { beforeAll, describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../testHelpers/l10n.js'

vi.mock('@nextcloud/l10n', () => createL10nMock())

let Settings: unknown

beforeAll(async () => {
	const settingsModule = await import('../../../views/Settings/Settings.vue')
	Settings = settingsModule.default
})

describe('Settings.vue', () => {
	it('renders the main settings sections container', () => {
		const wrapper = mount(Settings as never, {
			global: {
				stubs: {
					SupportProject: { template: '<div class="support-project-stub" />' },
					CertificateEngine: true,
					SignatureEngine: true,
					SettingsPolicyWorkbench: true,
					DownloadBinaries: true,
					ConfigureCheck: true,
					RootCertificateCfssl: true,
					RootCertificateOpenSsl: true,
					SigningMode: true,
				},
			},
		})

		expect(wrapper.find('.support-project-stub').exists()).toBe(true)
		expect(wrapper.findAllComponents({ name: 'SignatureEngine' })).toHaveLength(1)
		expect(wrapper.findAllComponents({ name: 'SettingsPolicyWorkbench' })).toHaveLength(1)
	})

	it('does not render SigningMode because the template gate is false', () => {
		const wrapper = mount(Settings as never, {
			global: {
				stubs: {
					SupportProject: true,
					CertificateEngine: true,
					SignatureEngine: true,
					SettingsPolicyWorkbench: true,
					DownloadBinaries: true,
					ConfigureCheck: true,
					RootCertificateCfssl: true,
					RootCertificateOpenSsl: true,
					SigningMode: { name: 'SigningMode', template: '<div class="signing-mode-stub" />' },
				},
			},
		})

		expect(wrapper.find('.signing-mode-stub').exists()).toBe(false)
	})
})
