/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { collectMetadataRealDefinition } from './collect-metadata/realDefinition'
import { docMdpRealDefinition } from './docmdp/realDefinition'
import { identificationDocumentsRealDefinition } from './identification-documents/realDefinition'
import { requestSignGroupsRealDefinition } from './request-sign-groups/realDefinition'
import { signatureFooterRealDefinition } from './signature-footer/realDefinition'
import { signatureFlowRealDefinition } from './signature-flow/realDefinition'
import { signatureTextRealDefinition } from './signature-text/realDefinition'
import type { RealPolicySettingDefinition } from './realTypes'

export const realDefinitions = {
	add_footer: signatureFooterRealDefinition,
	collect_metadata: collectMetadataRealDefinition,
	signature_flow: signatureFlowRealDefinition,
	docmdp: docMdpRealDefinition,
	identification_documents: identificationDocumentsRealDefinition,
	groups_request_sign: requestSignGroupsRealDefinition,
	signature_text: signatureTextRealDefinition,
} satisfies Record<string, RealPolicySettingDefinition>
