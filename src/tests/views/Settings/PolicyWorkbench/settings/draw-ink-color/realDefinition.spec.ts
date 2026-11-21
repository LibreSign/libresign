/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import { drawInkColorRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/draw-ink-color/realDefinition'
import { resolveDrawInkColor } from '../../../../../../views/Settings/PolicyWorkbench/settings/draw-ink-color/model'

vi.mock('@nextcloud/l10n', () => createL10nMock())

describe('drawInkColorRealDefinition', () => {
	it('is a system-only setting that defaults to black', () => {
		expect(drawInkColorRealDefinition.key).toBe('draw_ink_color')
		expect(drawInkColorRealDefinition.supportedScopes).toEqual(['system'])
		expect(drawInkColorRealDefinition.createEmptyValue()).toBe('#000000')
		expect(drawInkColorRealDefinition.getFallbackSystemDefault(null, null)).toBe('#000000')
		expect(drawInkColorRealDefinition.normalizeAllowChildOverride('system', true)).toBe(false)
		expect(drawInkColorRealDefinition.groupAdminBehavior?.canRenderPolicy?.(null)).toBe(false)
	})

	it('keeps the stored system value as fallback default', () => {
		expect(drawInkColorRealDefinition.getFallbackSystemDefault('#1f2a5a', 'system')).toBe('#1f2a5a')
		expect(drawInkColorRealDefinition.getFallbackSystemDefault('#1f2a5a', 'group')).toBe('#000000')
	})

	it.each([
		['#1F2A5A', '#1f2a5a'],
		['#000000', '#000000'],
		['red', 'Not configured'],
		[true, 'Not configured'],
	])('summarizes %s as %s', (value, summary) => {
		expect(drawInkColorRealDefinition.summarizeValue(value)).toBe(summary)
	})

	it('normalizes draft values to a hex color', () => {
		expect(drawInkColorRealDefinition.normalizeDraftValue(' #ABCDEF ')).toBe('#abcdef')
		expect(drawInkColorRealDefinition.normalizeDraftValue('unexpected')).toBe('#000000')
		expect(drawInkColorRealDefinition.hasSelectableDraftValue('unexpected')).toBe(false)
	})
})

describe('resolveDrawInkColor', () => {
	it.each([
		['#1f2a5a', '#1f2a5a'],
		[' #1F2A5A ', '#1f2a5a'],
		['#fff', null],
		['1f2a5a', null],
		['#12345g', null],
		[1, null],
		[null, null],
	])('resolves %s to %s', (value, expected) => {
		expect(resolveDrawInkColor(value)).toBe(expected)
	})
})
