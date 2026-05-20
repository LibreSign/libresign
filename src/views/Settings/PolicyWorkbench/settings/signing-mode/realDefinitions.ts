/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import SigningModeRuleEditor from './SigningModeRuleEditor.vue'
import WorkerConfigRuleEditor from './WorkerConfigRuleEditor.vue'
import {
	getDefaultWorkerConfig,
	normalizeSigningExecutionSettings,
	normalizeWorkerConfig,
	resolveSigningMode,
	serializeWorkerConfig,
} from './model'

export const signingModeRealDefinition: RealPolicySettingDefinition = {
	key: 'signing_mode',
	title: t('libresign', 'Signature processing'),
	description: t('libresign', 'Choose how LibreSign processes signatures and configure background workers when needed.'),
	visibleInGroupAdmin: false,
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
			return t('libresign', 'Process immediately')
		}

		const workerTypeLabel = settings.workerType === 'external'
			? t('libresign', 'External worker')
			: t('libresign', 'Local worker')

		if (settings.workerType === 'local') {
			return t('libresign', 'Process in background · {type} · {count} jobs', {
				type: workerTypeLabel,
				count: String(settings.parallelWorkers),
			})
		}

		return t('libresign', 'Process in background · {type}', { type: workerTypeLabel })
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		t('libresign', 'Lower-level customization is disabled for this setting'),
}

export const workerConfigRealDefinition: RealPolicySettingDefinition = {
	key: 'worker_config',
	title: t('libresign', 'Background workers'),
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
			? t('libresign', 'External worker')
			: t('libresign', 'Local worker')

		if (config.workerType === 'local') {
			return t('libresign', 'Service: {type} • Parallel workers: {count}', {
				type: workerTypeLabel,
				count: String(config.parallelWorkers),
			})
		}

		return t('libresign', 'Service: {type}', { type: workerTypeLabel })
	},
	formatAllowOverride: () => t('libresign', 'Lower-level customization is disabled for this setting'),
}
