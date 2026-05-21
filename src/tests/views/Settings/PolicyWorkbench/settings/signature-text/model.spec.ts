/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { toRuntimeRenderMode } from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-text/model'

describe('model.ts', () => {
	describe('toRuntimeRenderMode', () => {
		it('converts UI "default" to runtime "GRAPHIC_AND_DESCRIPTION"', () => {
			expect(toRuntimeRenderMode('default')).toBe('GRAPHIC_AND_DESCRIPTION')
		})

		it('converts UI "text" to runtime "SIGNAME_AND_DESCRIPTION"', () => {
			expect(toRuntimeRenderMode('text')).toBe('SIGNAME_AND_DESCRIPTION')
		})

		it('converts UI "graphic" to runtime "GRAPHIC_ONLY"', () => {
			expect(toRuntimeRenderMode('graphic')).toBe('GRAPHIC_ONLY')
		})

		it('converts UI "description_only" to runtime "DESCRIPTION_ONLY"', () => {
			expect(toRuntimeRenderMode('description_only')).toBe('DESCRIPTION_ONLY')
		})

		it('defaults to GRAPHIC_AND_DESCRIPTION for unknown values', () => {
			expect(toRuntimeRenderMode('unknown')).toBe('GRAPHIC_AND_DESCRIPTION')
		})

		it('handles null/undefined values', () => {
			expect(toRuntimeRenderMode(null)).toBe('GRAPHIC_AND_DESCRIPTION')
			expect(toRuntimeRenderMode(undefined)).toBe('GRAPHIC_AND_DESCRIPTION')
		})

		it('handles runtime values (forwards compatibility)', () => {
			// If someone passes a runtime value, it should normalize and convert back
			expect(toRuntimeRenderMode('GRAPHIC_AND_DESCRIPTION')).toBe('GRAPHIC_AND_DESCRIPTION')
			expect(toRuntimeRenderMode('GRAPHIC_ONLY')).toBe('GRAPHIC_ONLY')
			expect(toRuntimeRenderMode('DESCRIPTION_ONLY')).toBe('DESCRIPTION_ONLY')
			expect(toRuntimeRenderMode('SIGNAME_AND_DESCRIPTION')).toBe('SIGNAME_AND_DESCRIPTION')
		})
	})
})
