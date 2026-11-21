/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import DrawInkColorRuleEditor from './DrawInkColorRuleEditor.vue'
import { DEFAULT_DRAW_INK_COLOR, resolveDrawInkColor } from './model'

export const drawInkColorRealDefinition: RealPolicySettingDefinition = {
	key: 'draw_ink_color',
	// TRANSLATORS Policy title for the initial ink color of the pad where signers draw their signature.
	title: t('libresign', 'Default ink color for drawn signatures'),
	// TRANSLATORS Policy description. Signers can still choose another color while drawing.
	description: t('libresign', 'Set the color the signature drawing pad starts with. Signers can still choose another color while drawing.'),
	supportedScopes: ['system'],
	groupAdminBehavior: {
		canRenderPolicy: () => false,
	},
	editor: DrawInkColorRuleEditor,
	createEmptyValue: () => DEFAULT_DRAW_INK_COLOR,
	normalizeDraftValue: (value: EffectivePolicyValue) => resolveDrawInkColor(value) ?? DEFAULT_DRAW_INK_COLOR,
	hasSelectableDraftValue: (value: EffectivePolicyValue) => resolveDrawInkColor(value) !== null,
	normalizeAllowChildOverride: () => false,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return DEFAULT_DRAW_INK_COLOR
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		return resolveDrawInkColor(value)
			// TRANSLATORS Fallback policy summary shown when no valid ink color is set.
			?? t('libresign', 'Not configured')
	},
	formatAllowOverride: () => {
		// TRANSLATORS Policy inheritance message indicating group and user scopes cannot override this setting.
		return t('libresign', 'Lower-level customization is disabled for this setting')
	},
}
