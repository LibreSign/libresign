/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { useSigningOrder } from '../../composables/useSigningOrder.js'

type Signer = { signingOrder: number }

describe('useSigningOrder composable', () => {
	const { recalculateSigningOrders, normalizeSigningOrders } = useSigningOrder()

	describe('recalculateSigningOrders', () => {
		it('handles empty signers array', () => {
			const signers: Signer[] = []
			recalculateSigningOrders(signers, 0)
			expect(signers).toEqual([])
		})

		it('assigns order 1 to first signer', () => {
			const signers: Signer[] = [{ signingOrder: 0 }]
			recalculateSigningOrders(signers, 0)
			expect(signers[0].signingOrder).toBe(1)
		})

		it('increments following signers when inserting at start', () => {
			const signers: Signer[] = [
				{ signingOrder: 0 },
				{ signingOrder: 1 },
				{ signingOrder: 2 },
			]

			recalculateSigningOrders(signers, 0)

			expect(signers[0].signingOrder).toBe(1)
			expect(signers[1].signingOrder).toBe(2)
			expect(signers[2].signingOrder).toBe(3)
		})

		it('assigns next order when adding at end', () => {
			const signers: Signer[] = [
				{ signingOrder: 1 },
				{ signingOrder: 2 },
			]

			recalculateSigningOrders(signers, 1)

			expect(signers[1].signingOrder).toBe(2)
		})

		it('handles reordering with original orders when moving forward', () => {
			const signers: Signer[] = [
				{ signingOrder: 1 },
				{ signingOrder: 2 },
				{ signingOrder: 3 },
			]

			recalculateSigningOrders(signers, 2, [1, 2, 3], 0)

			expect(signers[2].signingOrder).toBeGreaterThanOrEqual(1)
		})

		it('handles reordering with original orders when moving backward', () => {
			const signers: Signer[] = [
				{ signingOrder: 1 },
				{ signingOrder: 2 },
				{ signingOrder: 3 },
			]

			recalculateSigningOrders(signers, 1, [1, 2, 3], 2)

			expect(signers[1].signingOrder).toBeGreaterThanOrEqual(1)
		})

		it('handles equal previous and next orders', () => {
			const signers: Signer[] = [
				{ signingOrder: 1 },
				{ signingOrder: 1 },
				{ signingOrder: 1 },
			]

			recalculateSigningOrders(signers, 1)

			expect(signers[1].signingOrder).toBeGreaterThanOrEqual(1)
		})

		it('handles descending orders', () => {
			const signers: Signer[] = [
				{ signingOrder: 3 },
				{ signingOrder: 2 },
				{ signingOrder: 1 },
			]

			recalculateSigningOrders(signers, 1)

			expect(signers[1].signingOrder).toBeGreaterThanOrEqual(1)
		})
	})

	describe('normalizeSigningOrders', () => {
		it('handles empty signers array when normalizing', () => {
			const signers: Signer[] = []

			normalizeSigningOrders(signers)

			expect(signers).toEqual([])
		})

		it('normalizes to start at 1', () => {
			const signers: Signer[] = [{ signingOrder: 5 }, { signingOrder: 6 }]
			normalizeSigningOrders(signers)
			expect(signers[0].signingOrder).toBe(1)
			expect(signers[1].signingOrder).toBe(2)
		})

		it('handles negative orders', () => {
			const signers: Signer[] = [
				{ signingOrder: -1 },
				{ signingOrder: 0 },
			]

			normalizeSigningOrders(signers)

			expect(signers[0].signingOrder).toBe(1)
			expect(signers[1].signingOrder).toBe(2)
		})

		it('closes gaps in the sequence', () => {
			const signers: Signer[] = [
				{ signingOrder: 1 },
				{ signingOrder: 5 },
				{ signingOrder: 10 },
			]

			normalizeSigningOrders(signers)

			expect(signers[0].signingOrder).toBe(1)
			expect(signers[1].signingOrder).toBe(2)
			expect(signers[2].signingOrder).toBe(3)
		})
	})
})
