/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const TEXT_SIGNATURE_MARGIN = 6
export const TEXT_SIGNATURE_MAX_HEIGHT_RATIO = 0.8

interface TextSignatureFontSizeOptions {
	canvasWidth: number
	canvasHeight: number
	// Width of the text measured at referenceFontSize
	measuredWidth: number
	referenceFontSize: number
	margin?: number
	maxHeightRatio?: number
}

/**
 * Largest font size (px) at which the text fits on one line inside the
 * canvas width minus the margin on both sides, capped at maxHeightRatio of
 * the canvas height. Text width grows linearly with the font size, so one
 * measurement is enough.
 */
export function computeTextSignatureFontSize({
	canvasWidth,
	canvasHeight,
	measuredWidth,
	referenceFontSize,
	margin = TEXT_SIGNATURE_MARGIN,
	maxHeightRatio = TEXT_SIGNATURE_MAX_HEIGHT_RATIO,
}: TextSignatureFontSizeOptions): number {
	const maxByHeight = canvasHeight * maxHeightRatio
	const availableWidth = canvasWidth - (margin * 2)
	if (availableWidth <= 0 || measuredWidth <= 0 || referenceFontSize <= 0) {
		return Math.max(0, Math.min(maxByHeight, referenceFontSize))
	}
	const maxByWidth = (availableWidth / measuredWidth) * referenceFontSize
	return Math.max(0, Math.min(maxByHeight, maxByWidth))
}

// Size used before single-line fitting existed; below it the text wraps instead.
export const TEXT_SIGNATURE_MIN_FONT_SIZE = 30

/**
 * Single line at the fitted size when that is at least the minimum size,
 * otherwise wrap at the minimum size.
 */
export function resolveTextSignatureLayout(options: TextSignatureFontSizeOptions): { fontSize: number, wrap: boolean } {
	const fontSize = computeTextSignatureFontSize(options)
	if (fontSize >= TEXT_SIGNATURE_MIN_FONT_SIZE) {
		return { fontSize, wrap: false }
	}
	return { fontSize: TEXT_SIGNATURE_MIN_FONT_SIZE, wrap: true }
}
