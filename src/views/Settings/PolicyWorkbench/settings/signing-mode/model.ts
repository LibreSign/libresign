/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../../types/index'

export type SigningModeValue = 'sync' | 'async'
export type WorkerTypeValue = 'local' | 'external'

export interface WorkerConfigPolicyValue {
	workerType: WorkerTypeValue
	parallelWorkers: number
}

export function resolveSigningMode(value: EffectivePolicyValue): SigningModeValue {
	if (value === 'async') {
		return 'async'
	}

	return 'sync'
}

export function resolveWorkerType(value: EffectivePolicyValue): WorkerTypeValue {
	if (value === 'external') {
		return 'external'
	}

	return 'local'
}

export function resolveParallelWorkers(value: EffectivePolicyValue): number {
	if (typeof value === 'number' && Number.isInteger(value) && value >= 1 && value <= 32) {
		return value
	}

	if (typeof value === 'string') {
		const parsed = Number.parseInt(value.trim(), 10)
		if (Number.isInteger(parsed) && parsed >= 1 && parsed <= 32) {
			return parsed
		}
	}

	return 4
}
export function clampParallelWorkers(value: number): number {
	if (value < 1) return 1
	if (value > 32) return 32
	return value
}

export function getDefaultWorkerConfig(): WorkerConfigPolicyValue {
	return {
		workerType: 'local',
		parallelWorkers: 4,
	}
}

export function normalizeWorkerConfig(rawValue: EffectivePolicyValue): WorkerConfigPolicyValue {
	let obj: Record<string, unknown> | null = null

	if (typeof rawValue === 'string') {
		try {
			obj = JSON.parse(rawValue) as Record<string, unknown>
		} catch {
			return getDefaultWorkerConfig()
		}
	} else if (typeof rawValue === 'object' && rawValue !== null) {
		obj = rawValue as Record<string, unknown>
	}

	if (obj) {
		const workerType = obj.worker_type === 'external' ? 'external' : 'local'
		const rawParallel = obj.parallel_workers
		let parallelWorkers: number
		if (typeof rawParallel === 'number' && Number.isFinite(rawParallel)) {
			parallelWorkers = clampParallelWorkers(Math.trunc(rawParallel))
		} else if (typeof rawParallel === 'string') {
			const parsed = Number.parseInt(rawParallel.trim(), 10)
			parallelWorkers = Number.isNaN(parsed) ? getDefaultWorkerConfig().parallelWorkers : clampParallelWorkers(parsed)
		} else {
			parallelWorkers = getDefaultWorkerConfig().parallelWorkers
		}
		return { workerType, parallelWorkers }
	}

	return getDefaultWorkerConfig()
}

export function serializeWorkerConfig(config: WorkerConfigPolicyValue): string {
	return JSON.stringify({
		worker_type: config.workerType,
		parallel_workers: config.parallelWorkers,
	})
}
