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
	})

	describe('normalizeSigningOrders', () => {
		it('normalizes to start at 1', () => {
			const signers: Signer[] = [{ signingOrder: 5 }, { signingOrder: 6 }]
			normalizeSigningOrders(signers)
			expect(signers[0].signingOrder).toBe(1)
			expect(signers[1].signingOrder).toBe(2)
		})
	})
})
