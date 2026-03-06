/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const useSigningOrder = () => {
	type Signer = { signingOrder: number }

	const normalizeSigningOrders = (signers: Signer[]): void => {
		if (signers.length === 0) {
			return
		}

		const firstOrder = signers[0].signingOrder

		if (firstOrder > 1) {
			const diff = firstOrder - 1
			for (let i = 0; i < signers.length; i++) {
				signers[i].signingOrder -= diff
			}
		} else if (firstOrder < 1) {
			const diff = 1 - firstOrder
			for (let i = 0; i < signers.length; i++) {
				signers[i].signingOrder += diff
			}
		}

		for (let i = 0; i < signers.length - 1; i++) {
			const current = signers[i].signingOrder
			const next = signers[i + 1].signingOrder
			const expectedNext = current + 1

			if (next > expectedNext) {
				const gap = next - expectedNext
				for (let j = i + 1; j < signers.length; j++) {
					signers[j].signingOrder -= gap
				}
			}
		}
	}

	const recalculateSigningOrders = (
		signers: Signer[],
		targetIndex: number,
		originalOrders: number[] | null = null,
		oldIndex: number | null = null,
	): void => {
		if (signers.length === 0) {
			return
		}

		const hasPrev = targetIndex > 0
		const hasNext = targetIndex < signers.length - 1
		const isLastPosition = targetIndex === signers.length - 1
		let prevOrder: number | null = null
		let nextOrder: number | null = null

		if (originalOrders !== null && oldIndex !== null) {
			if (targetIndex > oldIndex) {
				if (hasPrev) {
					prevOrder = originalOrders[targetIndex]
				}
				if (hasNext) {
					nextOrder = originalOrders[targetIndex + 1]
				}
			} else {
				if (hasPrev) {
					prevOrder = originalOrders[targetIndex - 1]
				}
				if (hasNext) {
					nextOrder = originalOrders[targetIndex]
				}
			}

			if (isLastPosition && hasPrev) {
				prevOrder = signers[targetIndex - 1].signingOrder
			}
		} else {
			if (hasPrev) {
				prevOrder = signers[targetIndex - 1].signingOrder
			}
			if (hasNext) {
				nextOrder = signers[targetIndex + 1].signingOrder
			}
		}

		let newOrder
		if (!hasPrev) {
			newOrder = 1
			for (let i = targetIndex + 1; i < signers.length; i++) {
				signers[i].signingOrder += 1
			}
		} else if (isLastPosition) {
			newOrder = (prevOrder ?? signers[targetIndex - 1].signingOrder) + 1
		} else if (hasNext && nextOrder !== null && prevOrder !== null && nextOrder > prevOrder) {
			newOrder = nextOrder
			for (let i = targetIndex + 1; i < signers.length; i++) {
				signers[i].signingOrder += 1
			}
		} else if (hasNext && nextOrder !== null && prevOrder !== null && nextOrder === prevOrder) {
			newOrder = prevOrder
		} else {
			newOrder = prevOrder ?? 1
		}

		signers[targetIndex].signingOrder = newOrder
		normalizeSigningOrders(signers)
	}

	return {
		normalizeSigningOrders,
		recalculateSigningOrders,
	}
}
