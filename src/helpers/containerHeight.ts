/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Estimate the minimum container height needed to keep the PDF preview area
 * visible on the first render, before PDFElements has had a chance to report
 * its real rendered height.
 *
 * Returns 160 as an unconditional floor so the spinner is always visible.
 */
export function estimateContainerHeightForFirstRender(height: number, zoom: number): number {
	if (!Number.isFinite(height) || height <= 0 || !Number.isFinite(zoom) || zoom <= 0) {
		return 160
	}
	return Math.max(160, Math.round((height * zoom) / 100) + 24)
}
