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

type ApproverLikeObject = { id?: unknown }

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

function isApproverLikeObject(value: unknown): value is ApproverLikeObject {
	return typeof value === 'object' && value !== null && 'id' in value
	}

function normalizeApprovers(value: unknown): string[] {
	if (!Array.isArray(value)) {
		return ['admin']
	}

	const approvers = value
		.map((entry): string => {
			if (typeof entry === 'string') {
				return entry.trim()
			}

			if (isApproverLikeObject(entry)) {
				const id = entry.id
				return typeof id === 'string' ? id.trim() : ''
			}

			return ''
		})
		.filter((entry) => entry.length > 0)

	return approvers.length > 0 ? approvers : ['admin']
}

function normalizeToPayload(value: EffectivePolicyValue): IdentificationDocumentsPayload {
	if (isIdentificationDocumentsPayload(value)) {
		return {
			enabled: value.enabled,
			approvers: normalizeApprovers(value.approvers),
		}
	}

	if (typeof value === 'object' && value !== null) {
		const obj = value as Record<string, unknown>
		if (typeof obj.enabled === 'boolean') {
			return {
				enabled: obj.enabled,
				approvers: normalizeApprovers(obj.approvers),
			}
		}
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
	// TRANSLATORS Policy title for requiring signers to provide identification documents before certificate issuance.
	title: t('libresign', 'Identification documents flow'),
	// TRANSLATORS Policy description explaining whether submitted identification documents must be approved.
	description: t('libresign', 'Control whether signers must submit identification documents for approval.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && policy?.canSaveAsUserDefault === true,
	},
	supportedScopes: ['system', 'group', 'user'],
	editor: IdentificationDocumentsRuleEditor,
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
			// TRANSLATORS Policy value meaning the identification-documents workflow is enabled.
			return t('libresign', 'Enabled')
		}

		if (resolved === false) {
			// TRANSLATORS Policy value meaning the identification-documents workflow is disabled.
			return t('libresign', 'Disabled')
		}

		// TRANSLATORS Fallback policy summary shown when no explicit identification-documents rule is set.
		return t('libresign', 'Not configured')
	},
	formatAllowOverride: (allowChildOverride: boolean) => {
		if (allowChildOverride) {
			// TRANSLATORS Policy inheritance message indicating group and account scopes may define their own identification-documents rule.
			return t('libresign', 'Groups and accounts can set their own rule')
		}

		// TRANSLATORS Policy inheritance message indicating child scopes must use the identification-documents rule defined here.
		return t('libresign', 'Groups and accounts must follow this value')
	},
}
