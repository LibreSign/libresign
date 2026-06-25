/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import SigningModeRuleEditor from './SigningModeRuleEditor.vue'
import WorkerConfigRuleEditor from './WorkerConfigRuleEditor.vue'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import {
	getDefaultWorkerConfig,
	normalizeSigningExecutionSettings,
	normalizeWorkerConfig,
	resolveSigningMode,
	serializeWorkerConfig,
} from './model'

export const signingModeRealDefinition: RealPolicySettingDefinition = {
	key: 'signing_mode',
	// TRANSLATORS Policy title controlling whether signatures are processed immediately or by background workers.
	title: t('libresign', 'Signature processing'),
	// TRANSLATORS Policy description for selecting synchronous/asynchronous signing and worker behavior.
	description: t('libresign', 'Choose how LibreSign processes signatures and configure background workers when needed.'),
	groupAdminBehavior: {
		canRenderPolicy: () => false,
	},
	editor: SigningModeRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => normalizeSigningExecutionSettings('sync'),
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizeSigningExecutionSettings(value),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: () => false,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return resolveSigningMode(policyValue)
		}

		return 'sync'
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const settings = normalizeSigningExecutionSettings(value)
		if (settings.signingMode === 'sync') {
			// TRANSLATORS Summary label for synchronous processing mode.
			return t('libresign', 'Process immediately')
		}

		const workerTypeLabel = settings.workerType === 'external'
			// TRANSLATORS Worker type label when background jobs are processed by an external worker service.
			? t('libresign', 'External worker')
			// TRANSLATORS Worker type label when background jobs are processed by built-in/local worker.
			: t('libresign', 'Local worker')

		if (settings.workerType === 'local') {
			// TRANSLATORS Summary for async local workers. {type} is worker label, {count} is parallel job count.
			return t('libresign', 'Process in background · {type} · {count} jobs', {
				type: workerTypeLabel,
				count: String(settings.parallelWorkers),
			})
		}

		// TRANSLATORS Summary for async external worker processing. {type} is worker label.
		return t('libresign', 'Process in background · {type}', { type: workerTypeLabel })
	},
	// TRANSLATORS Message indicating this setting can only be configured at higher scope.
	formatAllowOverride: () =>
		t('libresign', 'Lower-level customization is disabled for this setting'),
}

export const workerConfigRealDefinition: RealPolicySettingDefinition = {
	key: 'worker_config',
	// TRANSLATORS Policy title for configuration of background worker service and concurrency.
	title: t('libresign', 'Background workers'),
	// TRANSLATORS Policy description about asynchronous signing jobs and how many workers run in parallel.
	description: t('libresign', 'Configure asynchronous signing job processing and concurrency.'),
	supportedScopes: ['system'],
	editor: WorkerConfigRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => serializeWorkerConfig(getDefaultWorkerConfig()),
	normalizeDraftValue: (value: EffectivePolicyValue) => serializeWorkerConfig(normalizeWorkerConfig(value)),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: () => false,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return serializeWorkerConfig(normalizeWorkerConfig(policyValue))
		}

		return serializeWorkerConfig(getDefaultWorkerConfig())
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const config = normalizeWorkerConfig(value)
		const workerTypeLabel = config.workerType === 'external'
			// TRANSLATORS Worker type label when processing uses external service/queue.
			? t('libresign', 'External worker')
			// TRANSLATORS Worker type label when processing uses local built-in workers.
			: t('libresign', 'Local worker')

		if (config.workerType === 'local') {
			// TRANSLATORS Worker config summary. {type} is worker label and {count} is number of parallel workers.
			return t('libresign', 'Service: {type} • Parallel workers: {count}', {
				type: workerTypeLabel,
				count: String(config.parallelWorkers),
			})
		}

		// TRANSLATORS Worker config summary for external service mode. {type} is worker label.
		return t('libresign', 'Service: {type}', { type: workerTypeLabel })
	},
	// TRANSLATORS Message indicating this setting cannot be overridden at lower scopes.
	formatAllowOverride: () => t('libresign', 'Lower-level customization is disabled for this setting'),
}
