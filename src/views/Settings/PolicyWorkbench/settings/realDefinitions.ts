/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { docMdpRealDefinition } from './docmdp/realDefinition'
import { addFooterRealDefinition } from './footer/realDefinition'
import { signatureFlowRealDefinition } from './signature-flow/realDefinition'
import type { RealPolicySettingDefinition } from './realTypes'

export const realDefinitions = {
	add_footer: addFooterRealDefinition,
	signature_flow: signatureFlowRealDefinition,
	docmdp: docMdpRealDefinition,
} satisfies Record<string, RealPolicySettingDefinition>
