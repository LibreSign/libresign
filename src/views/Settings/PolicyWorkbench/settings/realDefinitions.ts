/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { approvalGroupsRealDefinition } from './approval-groups/realDefinition'
import { collectMetadataRealDefinition } from './collect-metadata/realDefinition'
import { confettiRealDefinition } from './confetti/realDefinition'
import { crlValidationRealDefinition } from './crl-validation/realDefinition'
import { defaultUserFolderRealDefinition } from './default-user-folder/realDefinition'
import { docMdpRealDefinition } from './docmdp/realDefinition'
import { envelopeRealDefinition } from './envelope/realDefinition'
import {
	expiryInDaysRealDefinition,
	maximumValidityRealDefinition,
	renewalIntervalRealDefinition,
} from './expiration-rules/realDefinitions'
import { identifyMethodsRealDefinition } from './identify-methods/realDefinition'
import { identificationDocumentsRealDefinition } from './identification-documents/realDefinition'
import { legalInformationRealDefinition } from './legal-information/realDefinition'
import { reminderRealDefinition } from './reminder/realDefinition'
import { requestSignGroupsRealDefinition } from './request-sign-groups/realDefinition'
import { signatureBackgroundRealDefinition } from './signature-background/realDefinition'
import { signatureFooterRealDefinition } from './signature-footer/realDefinition'
import { signatureFlowRealDefinition } from './signature-flow/realDefinition'
import { signatureHashAlgorithmRealDefinition } from './signature-hash-algorithm/realDefinition'
import { signatureTextRealDefinition } from './signature-text/realDefinition'
import { tsaRealDefinition } from './tsa/realDefinition'
import { validationAccessRealDefinition } from './validation-access/realDefinition'
import type { RealPolicySettingDefinition } from './realTypes'

export const realDefinitions = {
	approval_group: approvalGroupsRealDefinition,
	add_footer: signatureFooterRealDefinition,
	collect_metadata: collectMetadataRealDefinition,
	show_confetti_after_signing: confettiRealDefinition,
	crl_external_validation_enabled: crlValidationRealDefinition,
	signature_flow: signatureFlowRealDefinition,
	signature_hash_algorithm: signatureHashAlgorithmRealDefinition,
	docmdp: docMdpRealDefinition,
	envelope_enabled: envelopeRealDefinition,
	default_user_folder: defaultUserFolderRealDefinition,
	legal_information: legalInformationRealDefinition,
	maximum_validity: maximumValidityRealDefinition,
	renewal_interval: renewalIntervalRealDefinition,
	expiry_in_days: expiryInDaysRealDefinition,
	identify_methods: identifyMethodsRealDefinition,
	identification_documents: identificationDocumentsRealDefinition,
	reminder_settings: reminderRealDefinition,
	groups_request_sign: requestSignGroupsRealDefinition,
	make_validation_url_private: validationAccessRealDefinition,
	signature_background_type: signatureBackgroundRealDefinition,
	signature_text: signatureTextRealDefinition,
	tsa_settings: tsaRealDefinition,
} satisfies Record<string, RealPolicySettingDefinition>
