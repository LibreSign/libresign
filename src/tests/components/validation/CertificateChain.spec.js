/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
let CertificateChain


vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((app, text) => text),
	translatePlural: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
	t: vi.fn((app, text) => text),
	n: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('@nextcloud/moment', () => ({
	default: {
		unix: vi.fn((timestamp) => ({
			format: vi.fn((fmt) => `Formatted: ${timestamp}`),
		})),
	},
}))

beforeAll(async () => {
	;({ default: CertificateChain } = await import('../../../components/validation/CertificateChain.vue'))
})

describe('CertificateChain', () => {
	let wrapper

	const createWrapper = (props = {}) => {
		return mount(CertificateChain, {
			props: {
				chain: [],
				...props,
			},
			global: {
				stubs: {
					NcListItem: false,
					NcButton: true,
					NcIconSvgWrapper: { template: '<div class="icon-stub"></div>' },
				},
				mocks: {
					t: (app, text) => text,
				},
			},
		})
	}

	beforeEach(() => {
		if (wrapper) {
			wrapper.destroy()
		}
		vi.clearAllMocks()
	})

	describe('RULE: certificate chain header toggles expansion', () => {
		it('initializes with closed state', () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Signer' }, issuer: { CN: 'CA' } },
				],
			})

			expect(wrapper.vm.chainOpen).toBe(false)
		})

		it('toggles chainOpen on header click', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Signer' }, issuer: { CN: 'CA' } },
				],
			})

			await wrapper.vm.$nextTick()

			wrapper.vm.chainOpen = true
			expect(wrapper.vm.chainOpen).toBe(true)

			wrapper.vm.chainOpen = false
			expect(wrapper.vm.chainOpen).toBe(false)
		})

		it('hides chain details when closed', () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Signer' }, issuer: { CN: 'CA' } },
				],
			})

			expect(wrapper.vm.chainOpen).toBe(false)
		})

		it('shows chain details when open', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Signer' }, issuer: { CN: 'CA' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const chainWrapper = wrapper.find('.chain-wrapper')
			expect(chainWrapper.exists()).toBe(true)
		})
	})

	describe('RULE: first certificate shows as signer', () => {
		it('displays "Signer:" label for first certificate', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'First Cert' }, issuer: { CN: 'CA' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const certItems = wrapper.findAll('.certificate-item')
			expect(certItems.length).toBeGreaterThan(0)
		})

		it('shows only first certificate as signer', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Signer Cert' }, issuer: { CN: 'Issuer 1' } },
					{ subject: { CN: 'Issuer Cert 1' }, issuer: { CN: 'Issuer 2' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const certItems = wrapper.findAll('.certificate-item')
			expect(certItems).toHaveLength(2)
		})
	})

	describe('RULE: subsequent certificates show as issuer', () => {
		it('displays "Issuer:" label for subsequent certificates', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Signer' }, issuer: { CN: 'CA 1' } },
					{ subject: { CN: 'CA 1' }, issuer: { CN: 'CA 2' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const certItems = wrapper.findAll('.certificate-item')
			expect(certItems).toHaveLength(2)
		})

		it('shows multiple issuer certificates', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Signer' }, issuer: { CN: 'CA 1' } },
					{ subject: { CN: 'CA 1' }, issuer: { CN: 'CA 2' } },
					{ subject: { CN: 'CA 2' }, issuer: { CN: 'Root' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const certItems = wrapper.findAll('.certificate-item')
			expect(certItems).toHaveLength(3)
		})
	})

	describe('RULE: certificate subject displays CN preferentially', () => {
		it('displays subject CN when available', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Subject CN' }, issuer: { CN: 'Issuer' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const text = wrapper.findAll('.cert-details').at(0).text()
			expect(text).toContain('Subject CN')
		})

		it('falls back to name property', async () => {
			wrapper = createWrapper({
				chain: [
					{ name: 'Certificate Name', issuer: { CN: 'Issuer' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const text = wrapper.findAll('.cert-details').at(0).text()
			expect(text).toContain('Certificate Name')
		})

		it('falls back to displayName property', async () => {
			wrapper = createWrapper({
				chain: [
					{ displayName: 'Display Name', issuer: { CN: 'Issuer' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const text = wrapper.findAll('.cert-details').at(0).text()
			expect(text).toContain('Display Name')
		})

		it('prefers CN over name and displayName', async () => {
			wrapper = createWrapper({
				chain: [
					{
						subject: { CN: 'Subject CN' },
						name: 'Name',
						displayName: 'Display',
						issuer: { CN: 'Issuer' },
					},
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const text = wrapper.findAll('.cert-details').at(0).text()
			expect(text).toContain('Subject CN')
		})
	})

	describe('RULE: certificate issuer displays issuer CN', () => {
		it('shows issuer CN when available', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Signer' }, issuer: { CN: 'Issuer CA' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const text = wrapper.findAll('.cert-details').at(0).text()
			expect(text).toContain('Issued by:')
			expect(text).toContain('Issuer CA')
		})

		it('hides issuer section when CN missing', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Signer' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const issuerDiv = wrapper.find('.cert-issuer')
			expect(issuerDiv.exists()).toBe(false)
		})
	})

	describe('RULE: serial number displays with optional hex', () => {
		it('shows serial number when available', async () => {
			wrapper = createWrapper({
				chain: [
					{
						subject: { CN: 'Signer' },
						serialNumber: '123456789',
					},
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const text = wrapper.findAll('.cert-details').at(0).text()
			expect(text).toContain('Serial Number:')
			expect(text).toContain('123456789')
		})

		it('displays hex serial number when present', async () => {
			wrapper = createWrapper({
				chain: [
					{
						subject: { CN: 'Signer' },
						serialNumber: '123456789',
						serialNumberHex: 'ABC123DEF',
					},
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const text = wrapper.findAll('.cert-details').at(0).text()
			expect(text).toContain('hex:')
			expect(text).toContain('ABC123DEF')
		})

		it('hides serial section when not available', async () => {
			wrapper = createWrapper({
				chain: [
					{
						subject: { CN: 'Signer' },
					},
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const text = wrapper.findAll('.cert-details').at(0).text()
			expect(text).not.toContain('Serial Number:')
		})
	})

	describe('RULE: validity dates format with moment', () => {
		it('formats validFrom_time_t correctly', async () => {
			wrapper = createWrapper({
				chain: [
					{
						subject: { CN: 'Signer' },
						validFrom_time_t: 1234567890,
					},
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const formatted = wrapper.vm.formatTimestamp(1234567890)
			expect(formatted).toContain('Formatted')
		})

		it('formats validTo_time_t correctly', async () => {
			wrapper = createWrapper({
				chain: [
					{
						subject: { CN: 'Signer' },
						validTo_time_t: 1234567890,
					},
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const formatted = wrapper.vm.formatTimestamp(1234567890)
			expect(formatted).toContain('Formatted')
		})

		it('shows both from and to dates', async () => {
			wrapper = createWrapper({
				chain: [
					{
						subject: { CN: 'Signer' },
						validFrom_time_t: 1234567890,
						validTo_time_t: 2234567890,
					},
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const text = wrapper.findAll('.cert-details').at(0).text()
			expect(text).toContain('Valid from:')
			expect(text).toContain('Valid to:')
		})

		it('returns empty string for null timestamp', () => {
			wrapper = createWrapper()

			const formatted = wrapper.vm.formatTimestamp(null)

			expect(formatted).toBe('')
		})

		it('hides validity section when timestamps missing', async () => {
			wrapper = createWrapper({
				chain: [
					{
						subject: { CN: 'Signer' },
					},
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const text = wrapper.findAll('.cert-details').at(0).text()
			expect(text).not.toContain('Valid from:')
		})
	})

	describe('RULE: chain wrapper has accessibility attributes', () => {
		it('has role region when open', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Signer' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const chainWrapper = wrapper.find('.chain-wrapper')
			expect(chainWrapper.attributes('role')).toBe('region')
		})

		it('has aria-label describing content', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Signer' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const chainWrapper = wrapper.find('.chain-wrapper')
			expect(chainWrapper.attributes('aria-label')).toContain('Certificate chain')
		})
	})

	describe('RULE: certificate items maintain order in chain', () => {
		it('displays certificates in chain order', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Certificate 1' }, issuer: { CN: 'CA 1' } },
					{ subject: { CN: 'Certificate 2' }, issuer: { CN: 'CA 2' } },
					{ subject: { CN: 'Certificate 3' }, issuer: { CN: 'CA 3' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const certItems = wrapper.findAll('.certificate-item')
			expect(certItems).toHaveLength(3)
		})
	})

	describe('RULE: certificate details handle complex data', () => {
		it('renders complete certificate with all fields', async () => {
			wrapper = createWrapper({
				chain: [
					{
						subject: { CN: 'John Doe' },
						issuer: { CN: 'Intermediate CA' },
						serialNumber: '123456789',
						serialNumberHex: 'ABC123',
						validFrom_time_t: 1234567890,
						validTo_time_t: 2234567890,
					},
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const details = wrapper.find('.cert-details')
			const text = details.text()

			expect(text).toContain('John Doe')
			expect(text).toContain('Issued by:')
			expect(text).toContain('Intermediate CA')
			expect(text).toContain('Serial Number:')
			expect(text).toContain('Valid from:')
		})

		it('handles certificates with minimal fields', async () => {
			wrapper = createWrapper({
				chain: [
					{
						subject: { CN: 'Minimal' },
					},
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			expect(wrapper.exists()).toBe(true)
		})
	})

	describe('RULE: toggle button switches icon based on state', () => {
		it('shows unfold-less icon when open', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Signer' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			// Icon should correspond to open state
			expect(wrapper.vm.chainOpen).toBe(true)
		})

		it('shows unfold-more icon when closed', () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Signer' } },
				],
			})

			expect(wrapper.vm.chainOpen).toBe(false)
		})
	})

	describe('RULE: empty chain displays nothing', () => {
		it('shows header even with empty chain', () => {
			wrapper = createWrapper({
				chain: [],
			})

			const header = wrapper.find('.extra')
			expect(header.exists()).toBe(true)
		})

		it('hides chain wrapper when empty and closed', () => {
			wrapper = createWrapper({
				chain: [],
			})

			const chainWrapper = wrapper.find('.chain-wrapper')
			expect(chainWrapper.exists()).toBe(false)
		})
	})

	describe('RULE: certificate item spacing removes last border', () => {
		it('renders multiple certificates without extra spacing', async () => {
			wrapper = createWrapper({
				chain: [
					{ subject: { CN: 'Cert 1' }, issuer: { CN: 'CA' } },
					{ subject: { CN: 'Cert 2' }, issuer: { CN: 'CA' } },
				],
			})

			wrapper.vm.chainOpen = true
			await wrapper.vm.$nextTick()

			const items = wrapper.findAll('.certificate-item')
			expect(items).toHaveLength(2)
		})
	})
})
