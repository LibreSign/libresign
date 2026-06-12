/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import IdentificationDocumentsRuleEditor from './IdentificationDocumentsRuleEditor.vue'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'

export interface IdentificationDocumentsPayload {
	enabled: boolean
	approvers: string[]
}

const DEFAULT_IDENTIFICATION_DOCUMENTS_PAYLOAD: IdentificationDocumentsPayload = {
	enabled: false,
	approvers: ['admin'],
}

function isIdentificationDocumentsPayload(value: unknown): value is IdentificationDocumentsPayload {
	if (typeof value !== 'object' || value === null) {
		return false
	}
	const obj = value as Record<string, unknown>
	return typeof obj.enabled === 'boolean' && Array.isArray(obj.approvers)
}

function normalizeToPayload(value: EffectivePolicyValue): IdentificationDocumentsPayload {
	if (isIdentificationDocumentsPayload(value)) {
		return value
	}

	// Default fallback
	return DEFAULT_IDENTIFICATION_DOCUMENTS_PAYLOAD
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
	createEmptyValue: () => DEFAULT_IDENTIFICATION_DOCUMENTS_PAYLOAD,
	normalizeDraftValue: (value: EffectivePolicyValue) => normalizeToPayload(value),
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveIdentificationDocuments(value) !== null,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return normalizeToPayload(policyValue)
		}

		return DEFAULT_IDENTIFICATION_DOCUMENTS_PAYLOAD
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
