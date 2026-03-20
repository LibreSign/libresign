/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createL10nMock } from '../../testHelpers/l10n.js'

vi.mock('@nextcloud/l10n', () => createL10nMock())

import Settings from '../../../views/Settings/Settings.vue'

describe('Settings.vue', () => {
	it('renders the main settings sections container', () => {
		const wrapper = mount(Settings, {
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
					IdentificationFactors: true,
					ExpirationRules: true,
					Validation: true,
					CrlValidation: true,
					DocMDP: true,
					SigningMode: true,
					AllowedGroups: true,
					LegalInformation: true,
					IdentificationDocuments: true,
					CollectMetadata: true,
					SignatureStamp: true,
					SignatureHashAlgorithm: true,
					DefaultUserFolder: true,
					Envelope: true,
					Reminders: true,
					TSA: true,
				},
			},
		})

		expect(wrapper.find('.support-project-stub').exists()).toBe(true)
		expect(wrapper.findAllComponents({ name: 'SignatureEngine' })).toHaveLength(1)
		expect(wrapper.findAllComponents({ name: 'Reminders' })).toHaveLength(1)
	})

	it('does not render SigningMode because the template gate is false', () => {
		const wrapper = mount(Settings, {
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
					IdentificationFactors: true,
					ExpirationRules: true,
					Validation: true,
					CrlValidation: true,
					DocMDP: true,
					SigningMode: { name: 'SigningMode', template: '<div class="signing-mode-stub" />' },
					AllowedGroups: true,
					LegalInformation: true,
					IdentificationDocuments: true,
					CollectMetadata: true,
					SignatureStamp: true,
					SignatureHashAlgorithm: true,
					DefaultUserFolder: true,
					Envelope: true,
					Reminders: true,
					TSA: true,
				},
			},
		})

		expect(wrapper.find('.signing-mode-stub').exists()).toBe(false)
	})

	it('toggles frozen preview visibility', async () => {
		const wrapper = mount(Settings, {
			global: {
				stubs: {
					SupportProject: true,
					CertificateEngine: true,
					SignatureEngine: true,
					SettingsPolicyWorkbench: true,
					FrozenSettingsPolicyWorkbench: { template: '<div class="frozen-stub" />' },
					DownloadBinaries: true,
					ConfigureCheck: true,
					RootCertificateCfssl: true,
					RootCertificateOpenSsl: true,
					IdentificationFactors: true,
					ExpirationRules: true,
					Validation: true,
					CrlValidation: true,
					DocMDP: true,
					SigningMode: true,
					AllowedGroups: true,
					LegalInformation: true,
					IdentificationDocuments: true,
					CollectMetadata: true,
					SignatureStamp: true,
					SignatureHashAlgorithm: true,
					DefaultUserFolder: true,
					Envelope: true,
					Reminders: true,
					TSA: true,
				},
			},
		})

		expect(wrapper.find('.frozen-stub').exists()).toBe(false)

		const toggle = wrapper.find('[data-testid="toggle-frozen-preview"]')
		expect(toggle.exists()).toBe(true)

		await toggle.trigger('click')
		expect(wrapper.find('.frozen-stub').exists()).toBe(true)

		await toggle.trigger('click')
		expect(wrapper.find('.frozen-stub').exists()).toBe(false)
	})
})
