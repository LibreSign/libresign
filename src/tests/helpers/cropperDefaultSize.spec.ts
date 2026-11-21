/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { fitStencilToImage } from '../../helpers/cropperDefaultSize'

describe('fitStencilToImage', () => {
	it('fits the width when the image is narrower than the aspect ratio', () => {
		expect(fitStencilToImage({ width: 700, height: 1000 }, 3.5)).toEqual({ width: 700, height: 200 })
	})

	it('fits the height when the image is wider than the aspect ratio', () => {
		expect(fitStencilToImage({ width: 3000, height: 400 }, 3.5)).toEqual({ width: 1400, height: 400 })
	})

	it('covers the image instead of using a screen-pixel size on large photos', () => {
		expect(fitStencilToImage({ width: 3000, height: 1200 }, 3.5)).toEqual({ width: 3000, height: 3000 / 3.5 })
	})

	it('keeps the aspect ratio', () => {
		const size = fitStencilToImage({ width: 4032, height: 3024 }, 3.5)
		expect(size).toBeDefined()
		expect(size!.width / size!.height).toBeCloseTo(3.5)
	})

	it('returns undefined without a usable aspect ratio', () => {
		expect(fitStencilToImage({ width: 3000, height: 1200 }, undefined)).toBeUndefined()
		expect(fitStencilToImage({ width: 3000, height: 1200 }, 0)).toBeUndefined()
		expect(fitStencilToImage({ width: 3000, height: 1200 }, Number.NaN)).toBeUndefined()
	})

	it('returns undefined for an invalid image size', () => {
		expect(fitStencilToImage(undefined, 3.5)).toBeUndefined()
		expect(fitStencilToImage({ width: 0, height: 100 }, 3.5)).toBeUndefined()
		expect(fitStencilToImage({ width: Number.NaN, height: 100 }, 3.5)).toBeUndefined()
	})
})
