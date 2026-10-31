/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import NotificationDeliveryRuleEditor from './NotificationDeliveryRuleEditor.vue'
import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'

const DEFAULT_MODE = 'sync'

function normalizeMode(value: EffectivePolicyValue): 'sync' | 'async' {
	return value === 'async' ? 'async' : DEFAULT_MODE
}

export const notificationDeliveryRealDefinition: RealPolicySettingDefinition = {
	key: 'notification_delivery_mode',
	// TRANSLATORS Title for selecting how notifications are processed.
	title: t('libresign', 'Notification delivery'),
	// TRANSLATORS Description for the notification processing mode policy.
	description: t('libresign', 'Choose how LibreSign processes notifications. Background processing depends on the available job runners.'),
	supportedScopes: ['system'],
	groupAdminBehavior: {
		canRenderPolicy: () => false,
	},
	editor: NotificationDeliveryRuleEditor,
	createEmptyValue: () => DEFAULT_MODE,
	normalizeDraftValue: normalizeMode,
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: () => false,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return normalizeMode(policyValue)
		}
		return DEFAULT_MODE
	},
	summarizeValue: (value: EffectivePolicyValue) => value === 'async'
		? t('libresign', 'Background processing')
		: t('libresign', 'Process immediately'),
	formatAllowOverride: () => t('libresign', 'Lower-level customization is disabled for this setting'),
}
