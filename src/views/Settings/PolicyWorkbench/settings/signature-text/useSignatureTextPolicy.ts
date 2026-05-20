/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, type ComputedRef } from 'vue'
import { usePoliciesStore } from '../../../../../store/policies'
import {
	getDefaultSignatureTextPolicyConfig,
	normalizeSignatureTextPolicyConfig,
	type SignatureTextPolicyConfig,
} from './model'

const SIGNATURE_TEXT_DEFAULTS = getDefaultSignatureTextPolicyConfig()

interface SignatureTextValues extends SignatureTextPolicyConfig {
}

export type SignatureTextUiDefaults = SignatureTextPolicyConfig

export function getSignatureTextUiDefaults(): SignatureTextUiDefaults {
	return {
		...SIGNATURE_TEXT_DEFAULTS,
		renderMode: 'default',
	}
}

export function useSignatureTextPolicy(): { values: ComputedRef<SignatureTextValues> } {
	const policiesStore = usePoliciesStore()

	const values = computed<SignatureTextValues>(() => {
		const signatureTextPolicy = policiesStore.policies.signature_stamp

		return signatureTextPolicy?.effectiveValue
			? normalizeSignatureTextPolicyConfig(signatureTextPolicy.effectiveValue)
			: getSignatureTextUiDefaults()
	})

	return { values }
}
