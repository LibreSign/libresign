/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createL10nMock, interpolateL10n } from '../../testHelpers/l10n.js'

import CertificateContent from '../../../views/ReadCertificate/CertificateContent.vue'

type PurposeEntry = [boolean, boolean, string]

type CertificateData = {
	subject: { CN: string, emailAddress?: string }
	issuer: { CN: string }
	valid_from: string
	valid_to: string
	validTo_time_t: number
	crl_validation: string
	extensions: { keyUsage: string }
	purposes: Record<string, PurposeEntry>
}

const selectCustonOptionMock = vi.fn()

vi.mock('@nextcloud/l10n', () => createL10nMock({
	t: (_app: string, text: string, params?: Record<string, string | number>) => interpolateL10n(text, params),
}))

vi.mock('../../../helpers/certification', () => ({
	selectCustonOption: vi.fn((...args: unknown[]) => selectCustonOptionMock(...args)),
}))

vi.mock('@nextcloud/vue/components/NcSettingsSection', () => ({
	default: {
		name: 'NcSettingsSection',
		props: ['name'],
		template: '<section class="nc-settings-section-stub"><h3>{{ name }}</h3><slot /></section>',
	},
}))

vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({
	default: {
		name: 'NcNoteCard',
		props: ['type', 'heading'],
		template: '<article class="nc-note-card-stub"><h4>{{ heading }}</h4><slot /></article>',
	},
}))

vi.mock('@nextcloud/vue/components/NcChip', () => ({
	default: {
		name: 'NcChip',
		props: ['text'],
		template: '<span class="nc-chip-stub">{{ text }}<slot /></span>',
	},
}))

vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({
	default: {
		name: 'NcIconSvgWrapper',
		template: '<i class="nc-icon-svg-wrapper-stub" />',
	},
}))

describe('CertificateContent.vue', () => {
	const baseCertificate: CertificateData = {
		subject: { CN: 'Ada Lovelace', emailAddress: 'ada@example.com' },
		issuer: { CN: 'LibreSign CA' },
		valid_from: '2025-01-01',
		valid_to: '2027-01-01',
		validTo_time_t: Math.floor(new Date('2027-01-01T00:00:00Z').getTime() / 1000),
		crl_validation: 'valid',
		extensions: { keyUsage: 'Digital Signature' },
		purposes: {
			0: [true, true, 'codesign'] as PurposeEntry,
		},
	}

	const createWrapper = (certificate: CertificateData = baseCertificate, index = '0') => mount(CertificateContent, {
		props: {
			certificate,
			index,
		},
		global: {
			stubs: {
				CertificateContent: {
					name: 'CertificateContent',
					template: '<div class="certificate-content-recursive-stub" />',
				},
			},
		},
	})

	beforeEach(() => {
		selectCustonOptionMock.mockReset()
		selectCustonOptionMock.mockReturnValue({
			isSome: () => false,
		})
	})

	it('shows purposes only for the root certificate entry', async () => {
		const wrapper = createWrapper()

		expect(wrapper.vm.shouldShowPurposes).toBe(true)
		expect(wrapper.text()).toContain('Certificate purposes')

		await wrapper.setProps({ index: '1' })

		expect(wrapper.vm.shouldShowPurposes).toBe(false)
	})

	it('maps certificate validity and crl status to display metadata', () => {
		const wrapper = createWrapper()

		expect(wrapper.vm.certificateValidityStatus.text).toBe('Valid')
		expect(wrapper.vm.crlValidationStatus.text).toBe('Valid (Not Revoked)')

		const expiredWrapper = createWrapper({
			...baseCertificate,
			validTo_time_t: Math.floor(new Date('2020-01-01T00:00:00Z').getTime() / 1000),
			crl_validation: 'unexpected',
		})

		expect(expiredWrapper.vm.certificateValidityStatus.text).toBe('Expired')
		expect(expiredWrapper.vm.crlValidationStatus.text).toBe('Unknown Status')
	})

	it('formats labels using custom certification metadata when available', () => {
		selectCustonOptionMock.mockReturnValue({
			isSome: () => true,
			unwrap: () => ({ label: 'signerName' }),
		})

		const wrapper = createWrapper()

		expect(wrapper.vm.getLabelFromId('CN')).toBe('Signer Name')
		expect(wrapper.vm.camelCaseToTitleCase('serialNumberHex')).toBe('Serial Number Hex')
	})

	it('labels chain certificates according to their position and self-signed state', () => {
		const wrapper = createWrapper()

		expect(wrapper.vm.getChainCertificateLabel(0, baseCertificate)).toBe('Intermediate Certificate')
		expect(wrapper.vm.getChainCertificateLabel(1, {
			subject: { CN: 'Root' },
			issuer: { CN: 'Root' },
		})).toBe('Root Certificate (CA)')
		expect(wrapper.vm.getChainCertificateLabel(2, {
			subject: { CN: 'Leaf' },
			issuer: { CN: 'Issuer' },
		})).toBe('Certificate 3')
	})
})
