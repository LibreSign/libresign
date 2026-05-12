/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import IdentifyMethodsRuleEditor from './IdentifyMethodsRuleEditor.vue'

import { normalizeIdentifyMethodsPolicy, serializeIdentifyMethodsPolicy } from './model'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'

function getInitialIdentifyMethods(): string {
	const normalized = normalizeIdentifyMethodsPolicy(
		loadState<EffectivePolicyValue>('libresign', 'identify_methods', []),
	)

	const permissiveDefaults = normalized.map((entry) => ({
		...entry,
		requirement: 'optional' as const,
	}))

	return serializeIdentifyMethodsPolicy(
		permissiveDefaults,
	)
}

export const identifyMethodsRealDefinition: RealPolicySettingDefinition = {
	key: 'identify_methods',
	title: t('libresign', 'Identification factors'),
	description: t('libresign', 'Ways to identify a person who will sign a document.'),
	supportedScopes: ['system', 'group', 'user'],
	editor: IdentifyMethodsRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => getInitialIdentifyMethods(),
	normalizeDraftValue: (value: EffectivePolicyValue) => serializeIdentifyMethodsPolicy(normalizeIdentifyMethodsPolicy(value)),
	hasSelectableDraftValue: (value: EffectivePolicyValue) => {
		const normalized = normalizeIdentifyMethodsPolicy(value)
		return normalized.some((entry) => entry.enabled)
	},
	isBaselineSeedable: (value: EffectivePolicyValue) => normalizeIdentifyMethodsPolicy(value).length > 0,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return serializeIdentifyMethodsPolicy([])
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const normalized = normalizeIdentifyMethodsPolicy(value)
		if (normalized.length === 0) {
			return t('libresign', 'Default runtime behavior')
		}

		const enabled = normalized.filter((entry) => entry.enabled)
		if (enabled.length === 0) {
			return t('libresign', 'No enabled identification factor')
		}

		if (enabled.length <= 2) {
			return enabled.map((entry) => entry.friendly_name ?? entry.name).join(', ')
		}

		return t('libresign', '{count} enabled factors', { count: String(enabled.length) })
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}
