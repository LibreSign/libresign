/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import ParallelWorkersRuleEditor from './ParallelWorkersRuleEditor.vue'
import SigningModeRuleEditor from './SigningModeRuleEditor.vue'
import WorkerTypeRuleEditor from './WorkerTypeRuleEditor.vue'
import { resolveParallelWorkers, resolveSigningMode, resolveWorkerType } from './model'

export const signingModeRealDefinition: RealPolicySettingDefinition = {
	key: 'signing_mode',
	title: t('libresign', 'Signing execution mode'),
	description: t('libresign', 'Choose whether signatures run synchronously or in background processing.'),
	supportedScopes: ['system'],
	editor: SigningModeRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => 'sync',
	normalizeDraftValue: (value: EffectivePolicyValue) => resolveSigningMode(value),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: () => false,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return resolveSigningMode(policyValue)
		}

		return 'sync'
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		return resolveSigningMode(value) === 'async'
			? t('libresign', 'Asynchronous')
			: t('libresign', 'Synchronous')
	},
	formatAllowOverride: () => t('libresign', 'Lower-level customization is disabled for this setting'),
}

export const workerTypeRealDefinition: RealPolicySettingDefinition = {
	key: 'worker_type',
	title: t('libresign', 'Worker service type'),
	description: t('libresign', 'Choose if asynchronous jobs run in local or external workers.'),
	supportedScopes: ['system'],
	editor: WorkerTypeRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => 'local',
	normalizeDraftValue: (value: EffectivePolicyValue) => resolveWorkerType(value),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: () => false,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return resolveWorkerType(policyValue)
		}

		return 'local'
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		return resolveWorkerType(value) === 'external'
			? t('libresign', 'External worker')
			: t('libresign', 'Local worker')
	},
	formatAllowOverride: () => t('libresign', 'Lower-level customization is disabled for this setting'),
}

export const parallelWorkersRealDefinition: RealPolicySettingDefinition = {
	key: 'parallel_workers',
	title: t('libresign', 'Parallel workers'),
	description: t('libresign', 'Control how many worker processes run signing jobs concurrently.'),
	supportedScopes: ['system'],
	editor: ParallelWorkersRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => 4,
	normalizeDraftValue: (value: EffectivePolicyValue) => resolveParallelWorkers(value),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: () => false,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return resolveParallelWorkers(policyValue)
		}

		return 4
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		return t('libresign', '{count} workers', { count: String(resolveParallelWorkers(value)) })
	},
	formatAllowOverride: () => t('libresign', 'Lower-level customization is disabled for this setting'),
}
