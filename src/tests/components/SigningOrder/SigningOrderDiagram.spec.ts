/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { mdiCheckCircle, mdiClockOutline, mdiCircleOutline } from '@mdi/js'

// Mock @nextcloud packages before component imports
vi.mock('@nextcloud/logger', () => ({
	getLogger: vi.fn(() => ({
		error: vi.fn(),
		warn: vi.fn(),
		info: vi.fn(),
		debug: vi.fn(),
	})),
	getLoggerBuilder: vi.fn(() => ({
		setApp: vi.fn().mockReturnThis(),
		detectUser: vi.fn().mockReturnThis(),
		build: vi.fn(() => ({
			error: vi.fn(),
			warn: vi.fn(),
			info: vi.fn(),
			debug: vi.fn(),
		})),
	})),
}))

let SigningOrderDiagram: any

beforeAll(async () => {
	;({ default: SigningOrderDiagram } = await import('../../../components/SigningOrder/SigningOrderDiagram.vue'))
})

describe('SigningOrderDiagram', () => {
	let wrapper: any

	const createWrapper = (props = {}) => {
		return mount(SigningOrderDiagram, {
			propsData: {
				signers: [],
				senderName: 'Test Sender',
				...props,
			},
			stubs: {
				NcAvatar: true,
				NcChip: true,
				NcPopover: true,
				Check: true,
			},
			mocks: {
				t: (app: any, text: any) => text,
			},
		})
	}

	beforeEach(() => {
		if (wrapper) {
		}
	})

	describe('RULE: uniqueOrders extracts and sorts distinct signing orders', () => {
		it('returns sorted unique orders from signers', () => {
			wrapper = createWrapper({
				signers: [
					{ signingOrder: 2 },
					{ signingOrder: 1 },
					{ signingOrder: 2 },
					{ signingOrder: 3 },
				],
			})

			expect(wrapper.vm.uniqueOrders).toEqual([1, 2, 3])
		})

		it('defaults signingOrder to 1 when missing', () => {
			wrapper = createWrapper({
				signers: [
					{ signingOrder: 3 },
					{},
					{ signingOrder: 2 },
				],
			})

			expect(wrapper.vm.uniqueOrders).toEqual([1, 2, 3])
		})

		it('returns empty array when no signers', () => {
			wrapper = createWrapper({ signers: [] })

			expect(wrapper.vm.uniqueOrders).toEqual([])
		})
	})

	describe('RULE: getSignersByOrder filters signers by order', () => {
		it('returns signers matching specific order', () => {
			const signers = [
				{ id: 1, signingOrder: 1 },
				{ id: 2, signingOrder: 2 },
				{ id: 3, signingOrder: 1 },
			]
			wrapper = createWrapper({ signers })

			const result = wrapper.vm.getSignersByOrder(1)

			expect(result).toHaveLength(2)
			expect(result.map((s: any) => s.id)).toEqual([1, 3])
		})

		it('treats missing signingOrder as 1', () => {
			const signers = [
				{ id: 1 },
				{ id: 2, signingOrder: 2 },
			]
			wrapper = createWrapper({ signers })

			const result = wrapper.vm.getSignersByOrder(1)

			expect(result).toHaveLength(1)
			expect(result[0].id).toBe(1)
		})
	})

	describe('RULE: getSignerDisplayName uses fallback chain', () => {
		it('uses displayName when available', () => {
			wrapper = createWrapper()

			const name = wrapper.vm.getSignerDisplayName({ displayName: 'John Doe' })

			expect(name).toBe('John Doe')
		})

		it('falls back to first identifyMethod value', () => {
			wrapper = createWrapper()

			const name = wrapper.vm.getSignerDisplayName({
				identifyMethods: [{ value: 'john@example.com' }],
			})

			expect(name).toBe('john@example.com')
		})

		it('falls back to translation when nothing else available', () => {
			wrapper = createWrapper()

			const name = wrapper.vm.getSignerDisplayName({})

			expect(name).toBe('Signer')
		})

		it('prefers displayName over identifyMethods', () => {
			wrapper = createWrapper()

			const name = wrapper.vm.getSignerDisplayName({
				displayName: 'John Doe',
				identifyMethods: [{ value: 'john@example.com' }],
			})

			expect(name).toBe('John Doe')
		})
	})

	describe('RULE: getSignerIdentify formats contact based on method type', () => {
		it('returns email value directly for email method', () => {
			wrapper = createWrapper()

			const identify = wrapper.vm.getSignerIdentify({
				identifyMethods: [{ method: 'email', value: 'john@example.com' }],
			})

			expect(identify).toBe('john@example.com')
		})

		it('formats account method with colab.rio domain', () => {
			wrapper = createWrapper()

			const identify = wrapper.vm.getSignerIdentify({
				identifyMethods: [{ method: 'account', value: 'john.doe' }],
			})

			expect(identify).toBe('v.account.john.doe@colab.rio')
		})

		it('returns value as-is for other methods', () => {
			wrapper = createWrapper()

			const identify = wrapper.vm.getSignerIdentify({
				identifyMethods: [{ method: 'phone', value: '+5511999999999' }],
			})

			expect(identify).toBe('+5511999999999')
		})

		it('returns value for undefined method', () => {
			wrapper = createWrapper()

			const identify = wrapper.vm.getSignerIdentify({
				identifyMethods: [{ value: 'some-value' }],
			})

			expect(identify).toBe('some-value')
		})
	})

	describe('RULE: getIdentifyMethods extracts method names', () => {
		it('returns array of method names', () => {
			wrapper = createWrapper()

			const methods = wrapper.vm.getIdentifyMethods({
				identifyMethods: [
					{ method: 'email' },
					{ method: 'phone' },
				],
			})

			expect(methods).toEqual(['email', 'phone'])
		})

		it('returns empty array when no identifyMethods', () => {
			wrapper = createWrapper()

			const methods = wrapper.vm.getIdentifyMethods({})

			expect(methods).toEqual([])
		})
	})

	describe('RULE: getStatusLabel returns appropriate label based on signer state', () => {
		it('returns Signed for signed signers', () => {
			wrapper = createWrapper()

			const label = wrapper.vm.getStatusLabel({ signed: true })

			expect(label).toBe('Signed')
		})

		it('returns Draft when me.status is 0', () => {
			wrapper = createWrapper()

			const label = wrapper.vm.getStatusLabel({ me: { status: 0 } })

			expect(label).toBe('Draft')
		})

		it('returns Pending for unsigned without draft status', () => {
			wrapper = createWrapper()

			const label = wrapper.vm.getStatusLabel({})

			expect(label).toBe('Pending')
		})

		it('prioritizes signed over draft status', () => {
			wrapper = createWrapper()

			const label = wrapper.vm.getStatusLabel({
				signed: true,
				me: { status: 0 },
			})

			expect(label).toBe('Signed')
		})
	})

	describe('RULE: getStatusIconPath returns MDI icon path based on status', () => {
		it('returns check circle icon for signed', () => {
			wrapper = createWrapper()

			const icon = wrapper.vm.getStatusIconPath({ signed: true })

			expect(icon).toBe(mdiCheckCircle)
		})

		it('returns circle outline icon for draft', () => {
			wrapper = createWrapper()

			const icon = wrapper.vm.getStatusIconPath({ me: { status: 0 } })

			expect(icon).toBe(mdiCircleOutline)
		})

		it('returns clock outline icon for pending', () => {
			wrapper = createWrapper()

			const icon = wrapper.vm.getStatusIconPath({})

			expect(icon).toBe(mdiClockOutline)
		})
	})

	describe('RULE: getChipType returns appropriate chip variant', () => {
		it('returns success for signed', () => {
			wrapper = createWrapper()

			const type = wrapper.vm.getChipType({ signed: true })

			expect(type).toBe('success')
		})

		it('returns secondary for draft', () => {
			wrapper = createWrapper()

			const type = wrapper.vm.getChipType({ me: { status: 0 } })

			expect(type).toBe('secondary')
		})

		it('returns warning for pending', () => {
			wrapper = createWrapper()

			const type = wrapper.vm.getChipType({})

			expect(type).toBe('warning')
		})
	})

	describe('RULE: getStatusClass returns CSS class based on status', () => {
		it('returns signed class for signed signers', () => {
			wrapper = createWrapper()

			const cssClass = wrapper.vm.getStatusClass({ signed: true })

			expect(cssClass).toBe('signed')
		})

		it('returns draft class for draft status', () => {
			wrapper = createWrapper()

			const cssClass = wrapper.vm.getStatusClass({ me: { status: 0 } })

			expect(cssClass).toBe('draft')
		})

		it('returns pending class for unsigned', () => {
			wrapper = createWrapper()

			const cssClass = wrapper.vm.getStatusClass({})

			expect(cssClass).toBe('pending')
		})
	})

	describe('RULE: formatDate converts Unix timestamp to localized string', () => {
		it('formats timestamp to locale string', () => {
			wrapper = createWrapper()

			const formatted = wrapper.vm.formatDate(1704067200)

			expect(formatted).toMatch(/2024|Jan|1/)
		})

		it('returns empty string for null timestamp', () => {
			wrapper = createWrapper()

			const formatted = wrapper.vm.formatDate(null)

			expect(formatted).toBe('')
		})

		it('returns empty string for undefined timestamp', () => {
			wrapper = createWrapper()

			const formatted = wrapper.vm.formatDate(undefined)

			expect(formatted).toBe('')
		})

		it('returns empty string for zero timestamp', () => {
			wrapper = createWrapper()

			const formatted = wrapper.vm.formatDate(0)

			expect(formatted).toBe('')
		})
	})
})
