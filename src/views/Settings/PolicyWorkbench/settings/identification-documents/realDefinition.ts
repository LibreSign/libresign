/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import IdentificationDocumentsRuleEditor from './IdentificationDocumentsRuleEditor.vue'

interface IdentificationDocumentsPayload {
	enabled: boolean
	approvers: string[]
}

function isIdentificationDocumentsPayload(value: unknown): value is IdentificationDocumentsPayload {
	if (typeof value !== 'object' || value === null) {
		return false
	}
	const obj = value as Record<string, unknown>
	return typeof obj.enabled === 'boolean' && Array.isArray(obj.approvers)
}

function normalizeToPayload(value: EffectivePolicyValue): IdentificationDocumentsPayload {
	// Already structured payload
	if (isIdentificationDocumentsPayload(value)) {
		return value
	}

	// Legacy boolean-based values
	let enabled = false
	if (typeof value === 'boolean') {
		enabled = value
	} else if (typeof value === 'number') {
		enabled = value === 1
	} else if (typeof value === 'string') {
		const normalized = value.trim().toLowerCase()
		enabled = ['1', 'true'].includes(normalized)
	}

	return {
		enabled,
		approvers: ['admin'],
	}
}

function resolveIdentificationDocuments(value: EffectivePolicyValue): boolean | null {
	const payload = normalizeToPayload(value)
	return payload.enabled
}

export const identificationDocumentsRealDefinition: RealPolicySettingDefinition = {
	key: 'identification_documents',
	title: t('libresign', 'Identification documents flow'),
	description: t('libresign', 'Control whether signers must submit identification documents for approval.'),
	supportedScopes: ['system', 'group', 'user'],
	editor: IdentificationDocumentsRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => ({ enabled: false, approvers: ['admin'] }),
	normalizeDraftValue: (value: EffectivePolicyValue) => {
		return normalizeToPayload(value)
	},
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveIdentificationDocuments(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return normalizeToPayload(policyValue)
		}

		return { enabled: false, approvers: ['admin'] }
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const resolved = resolveIdentificationDocuments(value)
		if (resolved === true) {
			return t('libresign', 'Enabled')
		}

		if (resolved === false) {
			return t('libresign', 'Disabled')
		}

		return t('libresign', 'Not configured')
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups can set their own rule')
			: t('libresign', 'Groups must follow this value'),
}