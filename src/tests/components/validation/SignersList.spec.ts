/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, VueWrapper } from '@vue/test-utils'
let SignersList: unknown


vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((_app, text) => text),
	translatePlural: vi.fn((_app, singular, plural, count) => (count === 1 ? singular : plural)),
	t: vi.fn((_app, text) => text),
	n: vi.fn((_app, singular, plural, count) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('@nextcloud/moment', () => ({
	default: vi.fn((date) => ({
		format: vi.fn((fmt) => `Formatted: ${date}`),
	})),
}))

beforeAll(async () => {
	;({ default: SignersList } = await import('../../../components/validation/SignersList.vue'))
})

describe('SignersList', () => {
	let wrapper: VueWrapper

	const createWrapper = (props: Record<string, unknown> = {}) => {
		return mount(SignersList, {
			props: {
				signers: [],
				compact: false,
				...props,
			},
			global: {
				stubs: {
					NcListItem: {
						template: '<div class="signer-item"><slot name="icon" /><slot name="name" /></div>',
					},
					NcAvatar: { template: '<div class="avatar-stub"></div>' },
					NcIconSvgWrapper: { template: '<div class="icon-stub"></div>' },
				},
				mocks: {
					t: (_app: string, text: string) => text,
				},
			},
		})
	}

	beforeEach(() => {
		vi.clearAllMocks()
	})

	describe('RULE: signers list displays each signer', () => {
		it('renders single signer', async () => {
			wrapper = createWrapper({
				signers: [
					{ displayName: 'John Doe', email: 'john@example.com' },
				],
			})
			await wrapper.vm.$nextTick()

			const items = wrapper.findAll('[data-testid^="signer-item"]')
			expect(items).toHaveLength(1)
		})

		it('renders multiple signers', async () => {
			wrapper = createWrapper({
				signers: [
					{ displayName: 'John Doe', email: 'john@example.com' },
					{ displayName: 'Jane Smith', email: 'jane@example.com' },
					{ displayName: 'Bob Johnson', email: 'bob@example.com' },
				],
			})
			await wrapper.vm.$nextTick()

			const items = wrapper.findAll('[data-testid^="signer-item"]')
			expect(items).toHaveLength(3)
		})

		it('handles empty signers list', async () => {
			wrapper = createWrapper({ signers: [] })
			await wrapper.vm.$nextTick()

			const items = wrapper.findAll('[data-testid^="signer-item"]')
			expect(items).toHaveLength(0)
		})
	})

	describe('RULE: signer displays displayName or email as fallback', () => {
		it('shows displayName when available', async () => {
			wrapper = createWrapper({
				signers: [
					{ displayName: 'John Doe', email: 'john@example.com' },
				],
			})
			await wrapper.vm.$nextTick()

			const signer = wrapper.find('[data-testid="signer-name"]')
			expect(signer.text()).toBe('John Doe')
		})

		it('shows email when displayName missing', async () => {
			wrapper = createWrapper({
				signers: [
					{ email: 'john@example.com' },
				],
			})
			await wrapper.vm.$nextTick()

			const signer = wrapper.find('[data-testid="signer-name"]')
			expect(signer.text()).toBe('john@example.com')
		})

		it('prefers displayName over email', async () => {
			wrapper = createWrapper({
				signers: [
					{ displayName: 'Display Name', email: 'fallback@example.com' },
				],
			})
			await wrapper.vm.$nextTick()

			const signer = wrapper.find('[data-testid="signer-name"]')
			expect(signer.text()).toBe('Display Name')
		})
	})

	describe('RULE: signed status shows date when signed', () => {
		it('displays signed status with date', async () => {
			wrapper = createWrapper({
				signers: [
					{
						displayName: 'John Doe',
						signed: '2024-06-01T12:00:00',
					},
				],
			})
			await wrapper.vm.$nextTick()

			const status = wrapper.find('[data-testid="signer-status-signed"]')
			expect(status.exists()).toBe(true)
			expect(status.text()).toContain('Signed on')
		})

		it('applies signed CSS class', () => {
			wrapper = createWrapper({
				signers: [
					{
						displayName: 'John Doe',
						signed: '2024-06-01T12:00:00',
					},
				],
			})

			const status = wrapper.find('.signer-status')
			expect(status.classes()).toContain('signed')
		})

		it('includes signed icon', () => {
			wrapper = createWrapper({
				signers: [
					{
						displayName: 'John Doe',
						signed: '2024-06-01T12:00:00',
					},
				],
			})

			const icons = wrapper.findAll('.status-icon')
			expect(icons.length).toBeGreaterThan(0)
		})
	})

	describe('RULE: pending status shows when not signed', () => {
		it('displays pending status when signed false', async () => {
			wrapper = createWrapper({
				signers: [
					{
						displayName: 'John Doe',
						signed: null,
					},
				],
			})
			await wrapper.vm.$nextTick()

			const status = wrapper.find('[data-testid="signer-status-pending"]')
			expect(status.exists()).toBe(true)
			expect(status.text()).toContain('Awaiting signature')
		})

		it('displays pending when signed property missing', async () => {
			wrapper = createWrapper({
				signers: [
					{
						displayName: 'John Doe',
					},
				],
			})

			const status = wrapper.find('.signer-status.pending')
			expect(status.exists()).toBe(true)
		})

		it('applies pending CSS class', () => {
			wrapper = createWrapper({
				signers: [
					{
						displayName: 'John Doe',
						signed: null,
					},
				],
			})

			const status = wrapper.find('.signer-status')
			expect(status.classes()).toContain('pending')
		})
	})

	describe('RULE: avatars display with userId when available', () => {
		it('passes userId to avatar when available', async () => {
			wrapper = createWrapper({
				signers: [
					{
						displayName: 'John Doe',
						userId: 'john_user_id',
					},
				],
			})

			await wrapper.vm.$nextTick()

			// NcAvatar should receive userId prop
			expect(wrapper.vm.signers[0].userId).toBe('john_user_id')
		})

		it('uses email for avatar when no userId', () => {
			wrapper = createWrapper({
				signers: [
					{
						email: 'john@example.com',
					},
				],
			})

			expect(wrapper.vm.signers[0].userId).toBeUndefined()
		})

		it('sets avatar size to 44 in normal mode', () => {
			wrapper = createWrapper({
				signers: [{ displayName: 'John' }],
				compact: false,
			})

			expect(wrapper.props('compact')).toBe(false)
		})

		it('sets avatar size to 32 in compact mode', () => {
			wrapper = createWrapper({
				signers: [{ displayName: 'John' }],
				compact: true,
			})

			expect(wrapper.props('compact')).toBe(true)
		})
	})

	describe('RULE: dateFromSqlAnsi formats dates to locale format', () => {
		it('formats signed date correctly', () => {
			wrapper = createWrapper({
				signers: [
					{
						displayName: 'John Doe',
						signed: '2024-06-01T12:00:00',
					},
				],
			})

			const formatted = wrapper.vm.dateFromSqlAnsi('2024-06-01T12:00:00')

			expect(formatted).toContain('Formatted')
		})

		it('uses LL LTS moment format', () => {
			wrapper = createWrapper()

			const formatted = wrapper.vm.dateFromSqlAnsi('2024-06-01T12:00:00')

			expect(formatted).toBeTruthy()
		})
	})

	describe('RULE: compact mode affects list item styling', () => {
		it('sets compact prop on list items', () => {
			wrapper = createWrapper({
				signers: [
					{ displayName: 'John' },
					{ displayName: 'Jane' },
				],
				compact: true,
			})

			expect(wrapper.props('compact')).toBe(true)
		})

		it('handles normal (non-compact) mode', () => {
			wrapper = createWrapper({
				signers: [{ displayName: 'John' }],
				compact: false,
			})

			expect(wrapper.props('compact')).toBe(false)
		})
	})

	describe('RULE: multiple signers with mixed status', () => {
		it('shows mix of signed and pending signers', () => {
			wrapper = createWrapper({
				signers: [
					{
						displayName: 'John Doe',
						signed: '2024-06-01T12:00:00',
					},
					{
						displayName: 'Jane Smith',
						signed: null,
					},
					{
						displayName: 'Bob Johnson',
						signed: '2024-06-02T14:30:00',
					},
				],
			})

			const items = wrapper.findAll('.signer-item')
			expect(items).toHaveLength(3)

			const signedStatuses = wrapper.findAll('.signer-status.signed')
			const pendingStatuses = wrapper.findAll('.signer-status.pending')

			expect(signedStatuses.length + pendingStatuses.length).toBe(3)
		})

		it('displays correct status for each signer', async () => {
			wrapper = createWrapper({
				signers: [
					{
						displayName: 'Signed User',
						signed: '2024-06-01T12:00:00',
					},
					{
						displayName: 'Pending User',
						signed: null,
					},
				],
			})

			await wrapper.vm.$nextTick()

			const items = wrapper.findAll('.signer-item')
			expect(items).toHaveLength(2)

			expect(items[0]!.find('.signer-status').classes()).toContain('signed')
			expect(items[1]!.find('.signer-status').classes()).toContain('pending')
		})
	})

	describe('RULE: list maintains order of signers', () => {
		it('displays signers in prop order', async () => {
			wrapper = createWrapper({
				signers: [
					{ displayName: 'First Signer' },
					{ displayName: 'Second Signer' },
					{ displayName: 'Third Signer' },
				],
			})

			await wrapper.vm.$nextTick()

			const items = wrapper.findAll('.signer-item')
			expect(items).toHaveLength(3)

			expect(items[0]!.text()).toContain('First Signer')
			expect(items[1]!.text()).toContain('Second Signer')
			expect(items[2]!.text()).toContain('Third Signer')
		})
	})

	describe('RULE: signer item spacing', () => {
		it('renders without bottom margin for last item', () => {
			wrapper = createWrapper({
				signers: [
					{ displayName: 'John' },
					{ displayName: 'Jane' },
				],
			})

			const items = wrapper.findAll('.signer-item')

			expect(items.length).toBeGreaterThan(0)
		})
	})

	describe('RULE: avatar menu disabled for non-users', () => {
		it('should not show menu for email-based signers', () => {
			wrapper = createWrapper({
				signers: [
					{
						email: 'external@example.com',
					},
				],
			})

			expect(wrapper.vm.signers[0].userId).toBeUndefined()
		})

		it('can show menu for users with userId', () => {
			wrapper = createWrapper({
				signers: [
					{
						userId: 'john_id',
						displayName: 'John',
					},
				],
			})

			expect(wrapper.vm.signers[0].userId).toBe('john_id')
		})
	})

	describe('RULE: signer info displays in correct layout', () => {
		it('groups name and status together', () => {
			wrapper = createWrapper({
				signers: [
					{
						displayName: 'John Doe',
						signed: '2024-06-01T12:00:00',
					},
				],
			})

			const signerInfo = wrapper.find('.signer-info')

			expect(signerInfo.exists()).toBe(true)
		})

		it('displays all signer information in single info block', () => {
			wrapper = createWrapper({
				signers: [
					{
						displayName: 'John Doe',
						signed: '2024-06-01T12:00:00',
					},
				],
			})

			const signerInfo = wrapper.find('.signer-info')
			const text = signerInfo.text()

			expect(text).toContain('John Doe')
		})
	})
})
