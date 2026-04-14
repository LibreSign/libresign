/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	resolveSignatureFlowPayloadForRequest,
	resolveSignatureFlowMode,
	toRequestSignatureFlowOverride,
} from '../../../../views/Settings/PolicyWorkbench/settings/signature-flow/model'

describe('signature-flow model', () => {
	it('resolves scalar integer values to flow modes', () => {
		expect(resolveSignatureFlowMode(0)).toBe('none')
		expect(resolveSignatureFlowMode(1)).toBe('parallel')
		expect(resolveSignatureFlowMode(2)).toBe('ordered_numeric')
	})

	it('resolves explicit string values and rejects unsupported strings', () => {
		expect(resolveSignatureFlowMode('none')).toBe('none')
		expect(resolveSignatureFlowMode('parallel')).toBe('parallel')
		expect(resolveSignatureFlowMode('ordered_numeric')).toBe('ordered_numeric')
		expect(resolveSignatureFlowMode('invalid')).toBeNull()
	})

	it('resolves nested object values recursively using flow field', () => {
		expect(resolveSignatureFlowMode({ flow: 'ordered_numeric' })).toBe('ordered_numeric')
		expect(resolveSignatureFlowMode({ flow: { flow: 'parallel' } })).toBe('parallel')
		expect(resolveSignatureFlowMode({ flow: { flow: 0 } })).toBe('none')
	})

	it('maps unsupported or null flow values to parallel request override by default', () => {
		expect(toRequestSignatureFlowOverride(null)).toBe('parallel')
		expect(toRequestSignatureFlowOverride('none')).toBe('parallel')
		expect(toRequestSignatureFlowOverride('parallel')).toBe('parallel')
	})

	it('keeps ordered_numeric as request override', () => {
		expect(toRequestSignatureFlowOverride('ordered_numeric')).toBe('ordered_numeric')
	})

	it('returns null request payload when request-level override is not allowed', () => {
		expect(resolveSignatureFlowPayloadForRequest(false, 'ordered_numeric')).toBeNull()
		expect(resolveSignatureFlowPayloadForRequest(false, 'parallel')).toBeNull()
	})

	it('returns request payload when request-level override is allowed', () => {
		expect(resolveSignatureFlowPayloadForRequest(true, 'ordered_numeric')).toBe('ordered_numeric')
		expect(resolveSignatureFlowPayloadForRequest(true, 'parallel')).toBe('parallel')
		expect(resolveSignatureFlowPayloadForRequest(true, 'none')).toBe('parallel')
		expect(resolveSignatureFlowPayloadForRequest(true, null)).toBe('parallel')
	})
})
