/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	parallelWorkersRealDefinition,
	signingModeRealDefinition,
	workerTypeRealDefinition,
} from '../../../../views/Settings/PolicyWorkbench/settings/signing-mode/realDefinitions'
import { resolveParallelWorkers, resolveSigningMode, resolveWorkerType } from '../../../../views/Settings/PolicyWorkbench/settings/signing-mode/model'

describe('signing-mode policy real definitions', () => {
	it.each([
		['sync', 'sync'],
		['async', 'async'],
		['invalid', 'sync'],
		[null, 'sync'],
	])('normalizes signing mode %s to %s', (value, expected) => {
		expect(resolveSigningMode(value as never)).toBe(expected)
	})

	it.each([
		['local', 'local'],
		['external', 'external'],
		['invalid', 'local'],
		[null, 'local'],
	])('normalizes worker type %s to %s', (value, expected) => {
		expect(resolveWorkerType(value as never)).toBe(expected)
	})

	it.each([
		[1, 1],
		['6', 6],
		[' 8 ', 8],
		['invalid', 4],
		[0, 4],
	])('normalizes parallel workers %s to %s', (value, expected) => {
		expect(resolveParallelWorkers(value as never)).toBe(expected)
	})

	it('exposes policy definitions for the policy workbench', () => {
		expect(signingModeRealDefinition.key).toBe('signing_mode')
		expect(workerTypeRealDefinition.key).toBe('worker_type')
		expect(parallelWorkersRealDefinition.key).toBe('parallel_workers')
		expect(signingModeRealDefinition.supportedScopes).toEqual(['system'])
		expect(workerTypeRealDefinition.supportedScopes).toEqual(['system'])
		expect(parallelWorkersRealDefinition.supportedScopes).toEqual(['system'])
	})
})
