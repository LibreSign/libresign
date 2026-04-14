/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { docMdpRealDefinition } from './docmdp/realDefinition'
import { requestSignGroupsRealDefinition } from './request-sign-groups/realDefinition'
import { signatureFooterRealDefinition } from './signature-footer/realDefinition'
import { signatureFlowRealDefinition } from './signature-flow/realDefinition'
import type { RealPolicySettingDefinition } from './realTypes'

export const realDefinitions = {
	add_footer: signatureFooterRealDefinition,
	signature_flow: signatureFlowRealDefinition,
	docmdp: docMdpRealDefinition,
	groups_request_sign: requestSignGroupsRealDefinition,
} satisfies Record<string, RealPolicySettingDefinition>
