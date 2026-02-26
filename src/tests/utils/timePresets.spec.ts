/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { getTimePresetRange, getTimePresets } from '../../utils/timePresets.js'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
}))

describe('getTimePresets', () => {
	describe('business rule: should return all 5 presets with correct structure', () => {
		it('returns exactly 5 presets', () => {
			expect(getTimePresets()).toHaveLength(5)
		})

		it('each preset has id, label, start, end', () => {
			getTimePresets().forEach(preset => {
				expect(preset).toHaveProperty('id')
				expect(preset).toHaveProperty('label')
				expect(preset).toHaveProperty('start')
				expect(preset).toHaveProperty('end')
			})
		})

		it('returns presets with the expected ids in order', () => {
			const ids = getTimePresets().map(p => p.id)
			expect(ids).toEqual(['today', 'last-7', 'last-30', 'this-year', 'last-year'])
		})

		it('each preset start is before its end', () => {
			getTimePresets().forEach(preset => {
				expect(preset.start).toBeLessThan(preset.end)
			})
		})
	})

	describe('business rule: today preset should span the current day', () => {
		it('start is midnight of today', () => {
			const preset = getTimePresets().find(p => p.id === 'today')!
			const d = new Date(preset.start)
			expect(d.getHours()).toBe(0)
			expect(d.getMinutes()).toBe(0)
			expect(d.getSeconds()).toBe(0)
		})

		it('end is 23:59:59.999 of today', () => {
			const preset = getTimePresets().find(p => p.id === 'today')!
			const d = new Date(preset.end)
			expect(d.getHours()).toBe(23)
			expect(d.getMinutes()).toBe(59)
			expect(d.getSeconds()).toBe(59)
		})
	})

	describe('business rule: range widths should match their names', () => {
		const MS_PER_DAY = 24 * 60 * 60 * 1000

		it('last-7 spans approximately 7 days', () => {
			const preset = getTimePresets().find(p => p.id === 'last-7')!
			const days = (preset.end - preset.start) / MS_PER_DAY
			expect(days).toBeGreaterThanOrEqual(7)
			expect(days).toBeLessThan(8)
		})

		it('last-30 spans approximately 30 days', () => {
			const preset = getTimePresets().find(p => p.id === 'last-30')!
			const days = (preset.end - preset.start) / MS_PER_DAY
			expect(days).toBeGreaterThanOrEqual(30)
			expect(days).toBeLessThan(31)
		})

		it('this-year starts on January 1st', () => {
			const preset = getTimePresets().find(p => p.id === 'this-year')!
			const d = new Date(preset.start)
			expect(d.getMonth()).toBe(0)
			expect(d.getDate()).toBe(1)
			expect(d.getFullYear()).toBe(new Date().getFullYear())
		})

		it('last-year starts on January 1st of the previous year', () => {
			const preset = getTimePresets().find(p => p.id === 'last-year')!
			const d = new Date(preset.start)
			expect(d.getMonth()).toBe(0)
			expect(d.getDate()).toBe(1)
			expect(d.getFullYear()).toBe(new Date().getFullYear() - 1)
		})
	})

	describe('business rule: dates are computed fresh on each call', () => {
		beforeEach(() => {
			vi.useFakeTimers()
		})

		afterEach(() => {
			vi.useRealTimers()
		})

		it('returns different start for today when the date changes', () => {
			vi.setSystemTime(new Date('2026-01-01T00:00:00'))
			const presets1 = getTimePresets()

			vi.setSystemTime(new Date('2026-02-15T00:00:00'))
			const presets2 = getTimePresets()

			expect(presets1[0].start).not.toBe(presets2[0].start)
		})

		it('always uses the fake current date, not a cached value', () => {
			vi.setSystemTime(new Date('2026-06-15T12:00:00'))
			const preset = getTimePresets().find(p => p.id === 'this-year')!
			expect(new Date(preset.start).getFullYear()).toBe(2026)
		})
	})
})

describe('getTimePresetRange', () => {
	describe('business rule: should return null for missing or unknown ids', () => {
		it('returns null when presetId is empty string', () => {
			expect(getTimePresetRange('')).toBeNull()
		})

		it('returns null when presetId is null/undefined', () => {
			expect(getTimePresetRange(null as unknown as string)).toBeNull()
			expect(getTimePresetRange(undefined as unknown as string)).toBeNull()
		})

		it('returns null for unknown preset id', () => {
			expect(getTimePresetRange('unknown')).toBeNull()
			expect(getTimePresetRange('weekly')).toBeNull()
		})
	})

	describe('business rule: should return { start, end } for known ids', () => {
		it.each(['today', 'last-7', 'last-30', 'this-year', 'last-year'])('returns range for "%s"', (id) => {
			const range = getTimePresetRange(id)
			expect(range).not.toBeNull()
			expect(range!.start).toBeTypeOf('number')
			expect(range!.end).toBeTypeOf('number')
			expect(range!.start).toBeLessThan(range!.end)
		})

		it('range values match getTimePresets output', () => {
			const preset = getTimePresets().find(p => p.id === 'last-7')!
			const range = getTimePresetRange('last-7')!
			expect(range.start).toBe(preset.start)
			expect(range.end).toBe(preset.end)
		})
	})
})
