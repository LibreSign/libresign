/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import signingOrderMixin from '../../mixins/signingOrderMixin.js'

describe('signingOrderMixin', () => {
	describe('recalculateSigningOrders', () => {
		it('handles empty signers array', () => {
			const signers = []

			signingOrderMixin.methods.recalculateSigningOrders(signers, 0)

			expect(signers).toEqual([])
		})

		it('assigns order 1 to first signer', () => {
			const signers = [{ signingOrder: 0 }]

			signingOrderMixin.methods.recalculateSigningOrders(signers, 0)

			expect(signers[0].signingOrder).toBe(1)
		})

		it('increments following signers when inserting at start', () => {
			const signers = [
				{ signingOrder: 0 },
				{ signingOrder: 1 },
				{ signingOrder: 2 },
			]

			signingOrderMixin.methods.recalculateSigningOrders(signers, 0)

			expect(signers[0].signingOrder).toBe(1)
			expect(signers[1].signingOrder).toBe(2)
			expect(signers[2].signingOrder).toBe(3)
		})

		it('assigns next order when adding at end', () => {
			const signers = [
				{ signingOrder: 1 },
				{ signingOrder: 2 },
			]

			signingOrderMixin.methods.recalculateSigningOrders(signers, 1)

			expect(signers[1].signingOrder).toBe(2)
		})

		it('handles reordering with originalOrders - moving forward', () => {
			const signers = [
				{ signingOrder: 1 },
				{ signingOrder: 2 },
				{ signingOrder: 3 },
			]
			const originalOrders = [1, 2, 3]

			signingOrderMixin.methods.recalculateSigningOrders(signers, 2, originalOrders, 0)

			expect(signers[2].signingOrder).toBeGreaterThanOrEqual(1)
		})

		it('handles reordering with originalOrders - moving backward', () => {
			const signers = [
				{ signingOrder: 1 },
				{ signingOrder: 2 },
				{ signingOrder: 3 },
			]
			const originalOrders = [1, 2, 3]

			signingOrderMixin.methods.recalculateSigningOrders(signers, 1, originalOrders, 2)

			expect(signers[1].signingOrder).toBeGreaterThanOrEqual(1)
		})

		it('handles equal prev and next orders', () => {
			const signers = [
				{ signingOrder: 1 },
				{ signingOrder: 1 },
				{ signingOrder: 1 },
			]

			signingOrderMixin.methods.recalculateSigningOrders(signers, 1)

			expect(signers[1].signingOrder).toBeGreaterThanOrEqual(1)
		})

		it('handles descending orders', () => {
			const signers = [
				{ signingOrder: 3 },
				{ signingOrder: 2 },
				{ signingOrder: 1 },
			]

			signingOrderMixin.methods.recalculateSigningOrders(signers, 1)

			expect(signers[1].signingOrder).toBeGreaterThanOrEqual(1)
		})
	})

	describe('normalizeSigningOrders', () => {
		it('handles empty signers array', () => {
			const signers = []

			signingOrderMixin.methods.normalizeSigningOrders(signers)

			expect(signers).toEqual([])
		})

		it('normalizes to start at 1', () => {
			const signers = [
				{ signingOrder: 5 },
				{ signingOrder: 6 },
			]

			signingOrderMixin.methods.normalizeSigningOrders(signers)

			expect(signers[0].signingOrder).toBe(1)
			expect(signers[1].signingOrder).toBe(2)
		})

		it('handles negative orders', () => {
			const signers = [
				{ signingOrder: -1 },
				{ signingOrder: 0 },
			]

			signingOrderMixin.methods.normalizeSigningOrders(signers)

			expect(signers[0].signingOrder).toBe(1)
			expect(signers[1].signingOrder).toBe(2)
		})

		it('closes gaps in sequence', () => {
			const signers = [
				{ signingOrder: 1 },
				{ signingOrder: 5 },
				{ signingOrder: 10 },
			]

			signingOrderMixin.methods.normalizeSigningOrders(signers)

			expect(signers[0].signingOrder).toBe(1)
			expect(signers[1].signingOrder).toBe(2)
			expect(signers[2].signingOrder).toBe(3)
		})
	})
})
