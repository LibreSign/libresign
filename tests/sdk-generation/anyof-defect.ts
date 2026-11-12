/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { EffectivePolicyValue } from '../../build/sdk-generation/typescript/api'
// This defect-reproduction check passes only while invalid arrays are accepted.
// Missing generated output or a corrected union must fail compilation.
const accepted: EffectivePolicyValue = ['not a policy value']
void accepted
