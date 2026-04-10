/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { EffectivePolicyValue } from '../../../../types/index'

export interface RealPolicySettingDefinition {
	key: string
	title: string
	context?: string
	description: string
	editor: unknown
	createEmptyValue: () => EffectivePolicyValue
	summarizeValue: (value: EffectivePolicyValue) => string
	formatAllowOverride: (allowChildOverride: boolean) => string
}
