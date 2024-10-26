/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
export const SignatureImageDimensions = Object.freeze({
	height: 100,
	width: 350,
})

/**
 *
 * @param {number} a a
 * @param {number} b b
 */
function gcd(a, b) {
	return (b === 0) ? a : gcd(b, a % b)
}

let ratio = 0

const ratioData = {}

// lazy calc
Object.defineProperty(ratioData, 'value', {
	get() {
		if (ratio === 0) {
			ratio = gcd(SignatureImageDimensions.width, SignatureImageDimensions.height)
		}

		return ratio
	},
})

export const SignatureImageRatio = Object.freeze(ratioData)
