/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { collectMetadataRealDefinition } from './collect-metadata/realDefinition'
import { confettiRealDefinition } from './confetti/realDefinition'
import { crlValidationRealDefinition } from './crl-validation/realDefinition'
import { defaultUserFolderRealDefinition } from './default-user-folder/realDefinition'
import { docMdpRealDefinition } from './docmdp/realDefinition'
import { envelopeRealDefinition } from './envelope/realDefinition'
import {
	expiryInDaysRealDefinition,
	maximumValidityRealDefinition,
} from './expiration-rules/realDefinitions'
import { identificationDocumentsRealDefinition } from './identification-documents/realDefinition'
import { identifyMethodsRealDefinition } from './identify-methods/realDefinition'
import { legalInformationRealDefinition } from './legal-information/realDefinition'
import type { RealPolicySettingDefinition } from './realTypes'
import { reminderRealDefinition } from './reminder/realDefinition'
import { requestSignGroupsRealDefinition } from './request-sign-groups/realDefinition'
import { signatureFlowRealDefinition } from './signature-flow/realDefinition'
import { signatureFooterRealDefinition } from './signature-footer/realDefinition'
import { signatureHashAlgorithmRealDefinition } from './signature-hash-algorithm/realDefinition'
import { signatureTextRealDefinition } from './signature-text/realDefinition'
import {
	signingModeRealDefinition,
	workerConfigRealDefinition,
} from './signing-mode/realDefinitions'
import { tsaRealDefinition } from './tsa/realDefinition'
import { validationAccessRealDefinition } from './validation-access/realDefinition'

export const realDefinitions = {
	// 1. Who can sign & request
	groups_request_sign: { ...requestSignGroupsRealDefinition, category: 'who-can-sign' },
	identification_documents: { ...identificationDocumentsRealDefinition, category: 'who-can-sign' },
	identify_methods: { ...identifyMethodsRealDefinition, category: 'who-can-sign' },

	// 2. How signing works
	signature_flow: { ...signatureFlowRealDefinition, category: 'how-signing-works' },
	envelope_enabled: { ...envelopeRealDefinition, category: 'how-signing-works' },

	// 3. What the signer sees
	add_footer: { ...signatureFooterRealDefinition, category: 'signer-experience' },
	signature_stamp: { ...signatureTextRealDefinition, category: 'signer-experience' },
	show_confetti_after_signing: { ...confettiRealDefinition, category: 'signer-experience' },

	// 4. What gets recorded
	collect_metadata: { ...collectMetadataRealDefinition, category: 'what-gets-recorded' },
	legal_information: { ...legalInformationRealDefinition, category: 'what-gets-recorded' },

	// 5. Time & limits
	expiry_in_days: { ...expiryInDaysRealDefinition, category: 'time-and-limits' },
	maximum_validity: { ...maximumValidityRealDefinition, category: 'time-and-limits' },
	reminder_settings: { ...reminderRealDefinition, category: 'time-and-limits' },

	// 6. Trust & verification
	signature_hash_algorithm: { ...signatureHashAlgorithmRealDefinition, category: 'trust-and-verification' },
	docmdp: { ...docMdpRealDefinition, category: 'trust-and-verification' },
	tsa_settings: { ...tsaRealDefinition, category: 'trust-and-verification' },
	crl_external_validation_enabled: { ...crlValidationRealDefinition, category: 'trust-and-verification' },

	// 7. System behavior
	default_user_folder: { ...defaultUserFolderRealDefinition, category: 'system-behavior' },
	make_validation_url_private: { ...validationAccessRealDefinition, category: 'system-behavior' },
	signing_mode: { ...signingModeRealDefinition, category: 'system-behavior' },
	worker_config: { ...workerConfigRealDefinition, category: 'system-behavior' },
} satisfies Record<string, RealPolicySettingDefinition>
