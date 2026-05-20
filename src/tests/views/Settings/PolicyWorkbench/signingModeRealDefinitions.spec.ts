/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	signingModeRealDefinition,
	workerConfigRealDefinition,
} from '../../../../views/Settings/PolicyWorkbench/settings/signing-mode/realDefinitions'
import {
	getDefaultWorkerConfig,
	normalizeWorkerConfig,
	resolveParallelWorkers,
	resolveSigningMode,
	resolveWorkerType,
	serializeWorkerConfig,
} from '../../../../views/Settings/PolicyWorkbench/settings/signing-mode/model'

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
	])('resolveWorkerType: %s -> %s', (value, expected) => {
		expect(resolveWorkerType(value as never)).toBe(expected)
	})

	it.each([
		[1, 1],
		['6', 6],
		[' 8 ', 8],
		['invalid', 4],
		[0, 4],
	])('resolveParallelWorkers: %s -> %s', (value, expected) => {
		expect(resolveParallelWorkers(value as never)).toBe(expected)
	})

	describe('normalizeWorkerConfig', () => {
		it('returns defaults when given empty string', () => {
			expect(normalizeWorkerConfig('')).toEqual(getDefaultWorkerConfig())
		})

		it('returns defaults when given null', () => {
			expect(normalizeWorkerConfig(null)).toEqual(getDefaultWorkerConfig())
		})

		it('parses valid JSON with local type', () => {
			const json = JSON.stringify({ worker_type: 'local', parallel_workers: 8 })
			expect(normalizeWorkerConfig(json)).toEqual({ workerType: 'local', parallelWorkers: 8 })
		})

		it('parses valid JSON with external type', () => {
			const json = JSON.stringify({ worker_type: 'external', parallel_workers: 4 })
			expect(normalizeWorkerConfig(json)).toEqual({ workerType: 'external', parallelWorkers: 4 })
		})

		it('clamps out-of-range parallel_workers to default', () => {
			const json = JSON.stringify({ worker_type: 'local', parallel_workers: 999 })
			expect(normalizeWorkerConfig(json)).toEqual({ workerType: 'local', parallelWorkers: 32 })
		})

		it('defaults to local on invalid worker_type', () => {
			const json = JSON.stringify({ worker_type: 'invalid', parallel_workers: 3 })
			expect(normalizeWorkerConfig(json)).toEqual({ workerType: 'local', parallelWorkers: 3 })
		})
	})

	describe('serializeWorkerConfig', () => {
		it('serializes to JSON with snake_case keys', () => {
			const result = serializeWorkerConfig({ workerType: 'local', parallelWorkers: 6 })
			expect(JSON.parse(result)).toEqual({ worker_type: 'local', parallel_workers: 6 })
		})
	})

	it('exposes policy definitions for the policy workbench', () => {
		expect(signingModeRealDefinition.key).toBe('signing_mode')
		expect(workerConfigRealDefinition.key).toBe('worker_config')
		expect(signingModeRealDefinition.supportedScopes).toEqual(['system'])
		expect(workerConfigRealDefinition.supportedScopes).toEqual(['system'])
	})

	describe('workerConfigRealDefinition', () => {
		it('createEmptyValue returns default config JSON', () => {
			const value = workerConfigRealDefinition.createEmptyValue!()
			expect(JSON.parse(value as string)).toEqual({ worker_type: 'local', parallel_workers: 4 })
		})

		it('normalizeDraftValue round-trips a valid value', () => {
			const raw = JSON.stringify({ worker_type: 'external', parallel_workers: 2 })
			const result = workerConfigRealDefinition.normalizeDraftValue!(raw)
			expect(JSON.parse(result as string)).toEqual({ worker_type: 'external', parallel_workers: 2 })
		})

		it('normalizeDraftValue falls back to defaults for invalid input', () => {
			const result = workerConfigRealDefinition.normalizeDraftValue!(null)
			expect(JSON.parse(result as string)).toEqual({ worker_type: 'local', parallel_workers: 4 })
		})

		it('summarizeValue shows External worker for external type', () => {
			const raw = JSON.stringify({ worker_type: 'external', parallel_workers: 4 })
			expect(workerConfigRealDefinition.summarizeValue!(raw)).toBe('External worker')
		})

		it('summarizeValue shows type and count for local type', () => {
			const raw = JSON.stringify({ worker_type: 'local', parallel_workers: 8 })
			const summary = workerConfigRealDefinition.summarizeValue!(raw)
			expect(summary).toContain('8')
		})
	})
})
