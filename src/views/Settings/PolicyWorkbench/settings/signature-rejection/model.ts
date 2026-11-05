/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { CompoundPolicyWriteValues, EffectivePolicyValue } from '../../../../../types/index'
import type { components } from '../../../../../types/openapi/openapi'

export type SignatureRejectionBehavior = components['schemas']['SignatureRejectionBehavior']
export type SignatureRejectionCommentMode = components['schemas']['SignatureRejectionCommentMode']
export type SignatureRejectionVisibility = components['schemas']['SignatureRejectionVisibility']

export const SIGNATURE_REJECTION_ENABLED_KEY = 'rejection_enabled'
export const SIGNATURE_REJECTION_BEHAVIOR_KEY = 'rejection_behavior'
export const SIGNATURE_REJECTION_COMMENT_MODE_KEY = 'rejection_comment_mode'
export const SIGNATURE_REJECTION_VISIBILITY_KEY = 'rejection_visibility'
export const SIGNATURE_REJECTION_COMMENT_VISIBILITY_KEY = 'rejection_comment_visibility'
export const SIGNATURE_REJECTION_POLICY_KEYS = [
	SIGNATURE_REJECTION_ENABLED_KEY,
	SIGNATURE_REJECTION_BEHAVIOR_KEY,
	SIGNATURE_REJECTION_COMMENT_MODE_KEY,
	SIGNATURE_REJECTION_VISIBILITY_KEY,
	SIGNATURE_REJECTION_COMMENT_VISIBILITY_KEY,
] as const

export const SIGNATURE_REJECTION_BEHAVIORS: readonly SignatureRejectionBehavior[] = ['cancel', 'continue']
export const SIGNATURE_REJECTION_COMMENT_MODES: readonly SignatureRejectionCommentMode[] = ['disabled', 'optional', 'required']
export const SIGNATURE_REJECTION_VISIBILITIES: readonly SignatureRejectionVisibility[] = ['requester', 'participants', 'public']

export interface SignatureRejectionDraftValue {
	enabled: boolean
	behavior: SignatureRejectionBehavior
	commentMode: SignatureRejectionCommentMode
	visibility: SignatureRejectionVisibility
	commentVisibility: SignatureRejectionVisibility
}

export const DEFAULT_SIGNATURE_REJECTION: Readonly<SignatureRejectionDraftValue> = {
	enabled: false,
	behavior: 'cancel',
	commentMode: 'disabled',
	visibility: 'requester',
	commentVisibility: 'requester',
}

type Translate = (app: string, text: string, vars?: Record<string, string>, options?: { escape?: boolean }) => string

function pickOption<T extends string>(options: readonly T[], value: unknown, fallback: T): T {
	return typeof value === 'string' && (options as readonly string[]).includes(value.trim())
		? value.trim() as T
		: fallback
}

export function normalizeSignatureRejectionEnabled(value: unknown): boolean {
	if (typeof value === 'boolean') {
		return value
	}

	if (typeof value === 'number') {
		return value > 0
	}

	if (typeof value === 'string') {
		return ['1', 'true', 'yes', 'on'].includes(value.trim().toLowerCase())
	}

	return DEFAULT_SIGNATURE_REJECTION.enabled
}

export function normalizeSignatureRejectionBehavior(value: unknown): SignatureRejectionBehavior {
	return pickOption(SIGNATURE_REJECTION_BEHAVIORS, value, DEFAULT_SIGNATURE_REJECTION.behavior)
}

export function normalizeSignatureRejectionCommentMode(value: unknown): SignatureRejectionCommentMode {
	return pickOption(SIGNATURE_REJECTION_COMMENT_MODES, value, DEFAULT_SIGNATURE_REJECTION.commentMode)
}

export function normalizeSignatureRejectionVisibility(value: unknown, fallback: SignatureRejectionVisibility): SignatureRejectionVisibility {
	return pickOption(SIGNATURE_REJECTION_VISIBILITIES, value, fallback)
}

export function isSignatureRejectionDraftValue(value: unknown): value is SignatureRejectionDraftValue {
	return !!value && typeof value === 'object' && 'enabled' in value
}

/**
 * Every field is normalized on its own. Fields that do not apply while
 * rejection or the comment is disabled are kept as they are, so an
 * administrator can prepare them before turning the setting on.
 */
export function normalizeSignatureRejectionDraftValue(value: EffectivePolicyValue | unknown): SignatureRejectionDraftValue {
	if (!isSignatureRejectionDraftValue(value)) {
		return {
			...DEFAULT_SIGNATURE_REJECTION,
			enabled: normalizeSignatureRejectionEnabled(value),
		}
	}

	return {
		enabled: normalizeSignatureRejectionEnabled(value.enabled),
		behavior: normalizeSignatureRejectionBehavior(value.behavior),
		commentMode: normalizeSignatureRejectionCommentMode(value.commentMode),
		visibility: normalizeSignatureRejectionVisibility(value.visibility, DEFAULT_SIGNATURE_REJECTION.visibility),
		commentVisibility: normalizeSignatureRejectionVisibility(value.commentVisibility, DEFAULT_SIGNATURE_REJECTION.commentVisibility),
	}
}

export function composeSignatureRejectionValue(valuesByPolicyKey: Record<string, EffectivePolicyValue | undefined>): SignatureRejectionDraftValue {
	return normalizeSignatureRejectionDraftValue({
		enabled: valuesByPolicyKey[SIGNATURE_REJECTION_ENABLED_KEY],
		behavior: valuesByPolicyKey[SIGNATURE_REJECTION_BEHAVIOR_KEY],
		commentMode: valuesByPolicyKey[SIGNATURE_REJECTION_COMMENT_MODE_KEY],
		visibility: valuesByPolicyKey[SIGNATURE_REJECTION_VISIBILITY_KEY],
		commentVisibility: valuesByPolicyKey[SIGNATURE_REJECTION_COMMENT_VISIBILITY_KEY],
	})
}

export function decomposeSignatureRejectionValue(editorValue: EffectivePolicyValue): CompoundPolicyWriteValues {
	const normalized = normalizeSignatureRejectionDraftValue(editorValue)

	return {
		[SIGNATURE_REJECTION_ENABLED_KEY]: normalized.enabled,
		[SIGNATURE_REJECTION_BEHAVIOR_KEY]: normalized.behavior,
		[SIGNATURE_REJECTION_COMMENT_MODE_KEY]: normalized.commentMode,
		[SIGNATURE_REJECTION_VISIBILITY_KEY]: normalized.visibility,
		[SIGNATURE_REJECTION_COMMENT_VISIBILITY_KEY]: normalized.commentVisibility,
	}
}

export function getSignatureRejectionBehaviorLabel(behavior: SignatureRejectionBehavior, tFn: Translate): string {
	return behavior === 'continue'
		// TRANSLATORS Signature rejection option: the other signers can still sign after someone rejects.
		? tFn('libresign', 'Let the others keep signing')
		// TRANSLATORS Signature rejection option: the whole signing request is canceled once someone rejects.
		: tFn('libresign', 'Cancel the signing request')
}

export function getSignatureRejectionCommentModeLabel(commentMode: SignatureRejectionCommentMode, tFn: Translate): string {
	switch (commentMode) {
	case 'optional':
		// TRANSLATORS Signature rejection option: the signer may explain why they reject.
		return tFn('libresign', 'Optional comment')
	case 'required':
		// TRANSLATORS Signature rejection option: the signer must explain why they reject.
		return tFn('libresign', 'Required comment')
	default:
		// TRANSLATORS Signature rejection option: the signer cannot write a reason when rejecting.
		return tFn('libresign', 'No comment')
	}
}

export function getSignatureRejectionVisibilityLabel(visibility: SignatureRejectionVisibility, tFn: Translate): string {
	switch (visibility) {
	case 'participants':
		// TRANSLATORS Audience option: the requester and the other signers of the document.
		return tFn('libresign', 'Requester and signers')
	case 'public':
		// TRANSLATORS Audience option: anyone who can open the document validation page.
		return tFn('libresign', 'Anyone who can validate the document')
	default:
		// TRANSLATORS Audience option: only the person who requested the signatures.
		return tFn('libresign', 'Requester only')
	}
}

export function summarizeSignatureRejectionDraftValue(value: EffectivePolicyValue, tFn: Translate): string {
	const normalized = normalizeSignatureRejectionDraftValue(value)
	if (!normalized.enabled) {
		// TRANSLATORS Policy summary meaning signers cannot reject a signing request.
		return tFn('libresign', 'Disabled')
	}

	// TRANSLATORS Policy summary for signature rejection. {behavior} is what happens after a rejection, {comment} is whether a reason is asked.
	return tFn('libresign', '{behavior} · {comment}', {
		behavior: getSignatureRejectionBehaviorLabel(normalized.behavior, tFn),
		comment: getSignatureRejectionCommentModeLabel(normalized.commentMode, tFn),
	}, { escape: false })
}
