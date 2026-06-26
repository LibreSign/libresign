/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	clampParallelWorkers,
	getDefaultWorkerConfig,
	normalizeSigningExecutionSettings,
	normalizeWorkerConfig,
	resolveParallelWorkers,
	resolveSigningMode,
	resolveWorkerType,
	serializeWorkerConfig,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/signing-mode/model'

describe('signing-mode model', () => {
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

	it('clamps parallel workers to the supported range', () => {
		expect(clampParallelWorkers(0)).toBe(1)
		expect(clampParallelWorkers(8)).toBe(8)
		expect(clampParallelWorkers(999)).toBe(32)
	})

	it('returns and serializes the default worker config in canonical shape', () => {
		expect(getDefaultWorkerConfig()).toEqual({ workerType: 'local', parallelWorkers: 4 })
		expect(serializeWorkerConfig({ workerType: 'local', parallelWorkers: 6 })).toBe('{"worker_type":"local","parallel_workers":6}')
	})

	it('normalizes worker_config payloads from JSON and object input', () => {
		expect(normalizeWorkerConfig('')).toEqual(getDefaultWorkerConfig())
		expect(normalizeWorkerConfig(JSON.stringify({ worker_type: 'external', parallel_workers: '7' }))).toEqual({
			workerType: 'external',
			parallelWorkers: 7,
		})
		expect(normalizeWorkerConfig({ worker_type: 'local', parallel_workers: 999 } as never)).toEqual({
			workerType: 'local',
			parallelWorkers: 32,
		})
	})

	it('normalizes consolidated signing execution settings from legacy string and object payloads', () => {
		expect(normalizeSigningExecutionSettings('async')).toEqual({
			signingMode: 'async',
			workerType: 'local',
			parallelWorkers: 4,
		})

		expect(normalizeSigningExecutionSettings({
			signingMode: 'sync',
			workerType: 'external',
			parallelWorkers: 7,
		})).toEqual({
			signingMode: 'sync',
			workerType: 'external',
			parallelWorkers: 7,
		})
	})
})
