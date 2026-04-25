/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'

type SignerTimestampComponent = typeof import('../../../components/validation/SignerTimestamp.vue').default

type SignerTimestampData = {
	genTime?: string
	policy?: string
	policyName?: string
	hash?: string
	hashAlgorithm?: string
	serialNumber?: string | number
	authority?: string
	tsaName?: string
	cnHints?: { commonName?: string }
}

type SignerTimestampVm = {
	open: boolean
	authority: string
	policy: string
	hashAlgorithm: string
	serialNumber: string
	hasContent: boolean
	toggleAriaLabel: string
	$nextTick: () => Promise<void>
	dateFromSqlAnsi: (date?: string | number | null) => string
}

type SignerTimestampWrapper = VueWrapper<SignerTimestampVm>

let SignerTimestamp: SignerTimestampComponent

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('@nextcloud/moment', () => ({
	default: vi.fn((value: string) => ({
		format: vi.fn(() => `Formatted: ${value}`),
	})),
}))

beforeAll(async () => {
	;({ default: SignerTimestamp } = await import('../../../components/validation/SignerTimestamp.vue'))
})

describe('SignerTimestamp', () => {
	let wrapper!: SignerTimestampWrapper

	const createWrapper = (props: { timestamp?: SignerTimestampData } = {}): SignerTimestampWrapper => {
		return mount(SignerTimestamp, {
			props,
			global: {
				stubs: {
					NcListItem: false,
					NcButton: true,
					NcIconSvgWrapper: { template: '<div class="icon-stub"></div>' },
				},
				mocks: {
					t: (_app: string, text: string) => text,
				},
			},
		}) as unknown as SignerTimestampWrapper
	}

	beforeEach(() => {
		vi.clearAllMocks()
	})

	describe('RULE: renders nothing when timestamp has no meaningful content', () => {
		it('renders nothing when timestamp is undefined', () => {
			wrapper = createWrapper()
			expect(wrapper.find('.extra').exists()).toBe(false)
		})

		it('renders nothing when timestamp is an empty object', () => {
			wrapper = createWrapper({ timestamp: {} })
			expect(wrapper.find('.extra').exists()).toBe(false)
		})

		it('hasContent is false when all fields are absent', () => {
			wrapper = createWrapper({ timestamp: {} })
			expect(wrapper.vm.hasContent).toBe(false)
		})
	})

	describe('RULE: renders header when any timestamp field is present', () => {
		it('renders header when authority is provided', () => {
			wrapper = createWrapper({ timestamp: { authority: 'tsa.example.org' } })
			expect(wrapper.find('.extra').exists()).toBe(true)
			expect(wrapper.vm.hasContent).toBe(true)
		})

		it('renders header when cnHints.commonName is provided', () => {
			wrapper = createWrapper({ timestamp: { cnHints: { commonName: 'tsa.example.org' } } })
			expect(wrapper.find('.extra').exists()).toBe(true)
		})

		it('renders header when tsaName is provided', () => {
			wrapper = createWrapper({ timestamp: { tsaName: 'FreeTSA' } })
			expect(wrapper.find('.extra').exists()).toBe(true)
		})

		it('renders header when genTime is provided', () => {
			wrapper = createWrapper({ timestamp: { genTime: '2026-04-25T18:36:28+00:00' } })
			expect(wrapper.find('.extra').exists()).toBe(true)
		})

		it('renders header when policyName is provided', () => {
			wrapper = createWrapper({ timestamp: { policyName: '1.2.3.4.1' } })
			expect(wrapper.find('.extra').exists()).toBe(true)
		})

		it('renders header when hashAlgorithm is provided', () => {
			wrapper = createWrapper({ timestamp: { hashAlgorithm: 'SHA-256' } })
			expect(wrapper.find('.extra').exists()).toBe(true)
		})

		it('renders header when serialNumber is a string', () => {
			wrapper = createWrapper({ timestamp: { serialNumber: 'AABB' } })
			expect(wrapper.find('.extra').exists()).toBe(true)
		})

		it('renders header when serialNumber is a number', () => {
			wrapper = createWrapper({ timestamp: { serialNumber: 42 } })
			expect(wrapper.find('.extra').exists()).toBe(true)
		})
	})

	describe('RULE: details panel is hidden by default and toggles on click', () => {
		it('initializes with closed state', () => {
			wrapper = createWrapper({ timestamp: { authority: 'tsa.example.org' } })
			expect(wrapper.vm.open).toBe(false)
		})

		it('does not render detail panel when closed', () => {
			wrapper = createWrapper({ timestamp: { authority: 'tsa.example.org' } })
			expect(wrapper.find('.timestamp-wrapper').exists()).toBe(false)
		})

		it('renders detail panel when open', async () => {
			wrapper = createWrapper({ timestamp: { authority: 'tsa.example.org' } })
			wrapper.vm.open = true
			await wrapper.vm.$nextTick()
			expect(wrapper.find('.timestamp-wrapper').exists()).toBe(true)
		})

		it('toggles open state', () => {
			wrapper = createWrapper({ timestamp: { authority: 'tsa.example.org' } })
			wrapper.vm.open = true
			expect(wrapper.vm.open).toBe(true)
			wrapper.vm.open = false
			expect(wrapper.vm.open).toBe(false)
		})
	})

	describe('RULE: authority resolves with priority cnHints > authority > tsaName', () => {
		it('prefers cnHints.commonName over authority and tsaName', () => {
			wrapper = createWrapper({
				timestamp: {
					cnHints: { commonName: 'cn.example.org' },
					authority: 'auth.example.org',
					tsaName: 'tsa.example.org',
				},
			})
			expect(wrapper.vm.authority).toBe('cn.example.org')
		})

		it('falls back to authority when cnHints is absent', () => {
			wrapper = createWrapper({
				timestamp: {
					authority: 'auth.example.org',
					tsaName: 'tsa.example.org',
				},
			})
			expect(wrapper.vm.authority).toBe('auth.example.org')
		})

		it('falls back to tsaName when both cnHints and authority are absent', () => {
			wrapper = createWrapper({ timestamp: { tsaName: 'tsa.example.org' } })
			expect(wrapper.vm.authority).toBe('tsa.example.org')
		})

		it('returns empty string when no authority field is present', () => {
			wrapper = createWrapper({ timestamp: { genTime: '2026-01-01T00:00:00Z' } })
			expect(wrapper.vm.authority).toBe('')
		})
	})

	describe('RULE: policy resolves policyName before policy', () => {
		it('prefers policyName over policy', () => {
			wrapper = createWrapper({ timestamp: { policyName: 'named-policy', policy: 'raw-policy' } })
			expect(wrapper.vm.policy).toBe('named-policy')
		})

		it('falls back to policy when policyName is absent', () => {
			wrapper = createWrapper({ timestamp: { policy: 'raw-policy' } })
			expect(wrapper.vm.policy).toBe('raw-policy')
		})

		it('returns empty string when neither is present', () => {
			wrapper = createWrapper({ timestamp: { authority: 'tsa.example.org' } })
			expect(wrapper.vm.policy).toBe('')
		})
	})

	describe('RULE: hashAlgorithm resolves hashAlgorithm before hash', () => {
		it('prefers hashAlgorithm over hash', () => {
			wrapper = createWrapper({ timestamp: { hashAlgorithm: 'SHA-256', hash: 'SHA-1' } })
			expect(wrapper.vm.hashAlgorithm).toBe('SHA-256')
		})

		it('falls back to hash when hashAlgorithm is absent', () => {
			wrapper = createWrapper({ timestamp: { hash: 'SHA-1' } })
			expect(wrapper.vm.hashAlgorithm).toBe('SHA-1')
		})
	})

	describe('RULE: serialNumber is coerced to string', () => {
		it('returns string serial as-is', () => {
			wrapper = createWrapper({ timestamp: { serialNumber: 'AABB' } })
			expect(wrapper.vm.serialNumber).toBe('AABB')
		})

		it('converts numeric serial to string', () => {
			wrapper = createWrapper({ timestamp: { serialNumber: 42 } })
			expect(wrapper.vm.serialNumber).toBe('42')
		})

		it('returns empty string when serialNumber is absent', () => {
			wrapper = createWrapper({ timestamp: { authority: 'tsa.example.org' } })
			expect(wrapper.vm.serialNumber).toBe('')
		})
	})

	describe('RULE: toggleAriaLabel reflects open state', () => {
		it('shows "Expand" label when closed', () => {
			wrapper = createWrapper({ timestamp: { authority: 'tsa.example.org' } })
			expect(wrapper.vm.toggleAriaLabel).toBe('Expand timestamp authority details')
		})

		it('shows "Collapse" label when open', () => {
			wrapper = createWrapper({ timestamp: { authority: 'tsa.example.org' } })
			wrapper.vm.open = true
			expect(wrapper.vm.toggleAriaLabel).toBe('Collapse timestamp authority details')
		})
	})

	describe('RULE: detail fields render when open and data is present', () => {
		const fullTimestamp: SignerTimestampData = {
			cnHints: { commonName: 'tsa.example.org' },
			genTime: '2026-04-25T18:36:28+00:00',
			policyName: '1.2.3.4.1',
			hashAlgorithm: 'SHA-256',
			serialNumber: '01AB',
		}

		it('renders authority field', async () => {
			wrapper = createWrapper({ timestamp: fullTimestamp })
			wrapper.vm.open = true
			await wrapper.vm.$nextTick()
			expect(wrapper.find('.timestamp-wrapper').text()).toContain('tsa.example.org')
		})

		it('renders policy field', async () => {
			wrapper = createWrapper({ timestamp: fullTimestamp })
			wrapper.vm.open = true
			await wrapper.vm.$nextTick()
			expect(wrapper.find('.timestamp-wrapper').text()).toContain('1.2.3.4.1')
		})

		it('renders hashAlgorithm field', async () => {
			wrapper = createWrapper({ timestamp: fullTimestamp })
			wrapper.vm.open = true
			await wrapper.vm.$nextTick()
			expect(wrapper.find('.timestamp-wrapper').text()).toContain('SHA-256')
		})

		it('renders serialNumber field', async () => {
			wrapper = createWrapper({ timestamp: fullTimestamp })
			wrapper.vm.open = true
			await wrapper.vm.$nextTick()
			expect(wrapper.find('.timestamp-wrapper').text()).toContain('01AB')
		})

		it('does not render absent optional fields', async () => {
			wrapper = createWrapper({ timestamp: { authority: 'tsa.example.org' } })
			wrapper.vm.open = true
			await wrapper.vm.$nextTick()
			const fields = wrapper.findAll('.timestamp-field')
			expect(fields).toHaveLength(1)
		})
	})

	describe('RULE: dateFromSqlAnsi formats dates via Moment', () => {
		it('returns empty string for falsy input', () => {
			wrapper = createWrapper({ timestamp: { authority: 'tsa.example.org' } })
			expect(wrapper.vm.dateFromSqlAnsi(null)).toBe('')
			expect(wrapper.vm.dateFromSqlAnsi(undefined)).toBe('')
			expect(wrapper.vm.dateFromSqlAnsi('')).toBe('')
		})

		it('formats a valid date string', () => {
			wrapper = createWrapper({ timestamp: { authority: 'tsa.example.org' } })
			const result = wrapper.vm.dateFromSqlAnsi('2026-04-25T18:36:28+00:00')
			expect(result).toContain('Formatted:')
		})
	})
})
