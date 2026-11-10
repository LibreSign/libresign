/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { EffectivePolicyValue } from '../../build/sdk-generation/typescript/api'
// Arrays are not one of the allowed anyOf alternatives. An unused directive
// means the generated type incorrectly accepts the invalid value.
// @ts-expect-error arrays are not policy values
const invalid: EffectivePolicyValue = ['not a policy value']
void invalid
