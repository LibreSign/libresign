/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, type ComputedRef } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { usePoliciesStore } from '../../../../../store/policies'
import {
	getDefaultSignatureTextPolicyConfig,
	normalizeSignatureTextPolicyConfig,
	type SignatureTextPolicyConfig,
} from './model'

const SIGNATURE_TEXT_DEFAULTS = getDefaultSignatureTextPolicyConfig()

interface SignatureTextValues extends SignatureTextPolicyConfig {
	templateError: string
	parsed: string
}

export type SignatureTextUiDefaults = SignatureTextPolicyConfig

export function getSignatureTextUiDefaults(): SignatureTextUiDefaults {
	return {
		...SIGNATURE_TEXT_DEFAULTS,
		renderMode: 'GRAPHIC_AND_DESCRIPTION',
	}
}

export function useSignatureTextPolicy(): { values: ComputedRef<SignatureTextValues> } {
	const policiesStore = usePoliciesStore()

	const values = computed<SignatureTextValues>(() => {
		const signatureTextPolicy = policiesStore.policies.signature_text

		const policyValue = signatureTextPolicy?.effectiveValue
			? normalizeSignatureTextPolicyConfig(signatureTextPolicy.effectiveValue)
			: getSignatureTextUiDefaults()

		// Only non-policy values come from loadState (error/parsing results)
		return {
			...policyValue,
			templateError: loadState<string>('libresign', 'signature_text_template_error', ''),
			parsed: loadState<string>('libresign', 'signature_text_parsed', ''),
		}
	})

	return { values }
}
