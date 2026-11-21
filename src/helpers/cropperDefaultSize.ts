/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export type CropperImageSize = {
	width: number
	height: number
}

/**
 * Largest stencil that fits inside the image while keeping the given aspect
 * ratio (width / height). Input and result are in image pixels, which is the
 * unit vue-advanced-cropper expects from a `defaultSize` function.
 */
export function fitStencilToImage(
	imageSize: CropperImageSize | undefined,
	aspectRatio: number | undefined,
): CropperImageSize | undefined {
	if (!imageSize || !aspectRatio || !Number.isFinite(aspectRatio) || aspectRatio <= 0) {
		return undefined
	}
	const { width, height } = imageSize
	if (!Number.isFinite(width) || !Number.isFinite(height) || width <= 0 || height <= 0) {
		return undefined
	}
	if (width / height <= aspectRatio) {
		return { width, height: width / aspectRatio }
	}
	return { width: height * aspectRatio, height }
}
