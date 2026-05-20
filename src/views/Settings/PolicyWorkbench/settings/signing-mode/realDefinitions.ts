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
	normalizeWorkerConfig,
	resolveSigningMode,
	serializeWorkerConfig,
} from './model'

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

export const workerConfigRealDefinition: RealPolicySettingDefinition = {
	key: 'worker_config',
	title: t('libresign', 'Worker configuration'),
	description: t('libresign', 'Choose whether workers run locally or externally, and set the parallelism level.'),
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
			return t('libresign', '{type} • {count} parallel', { type: workerTypeLabel, count: String(config.parallelWorkers) })
		}

		return workerTypeLabel
	},
	formatAllowOverride: () => t('libresign', 'Lower-level customization is disabled for this setting'),
}
