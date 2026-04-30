/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import ReminderRuleEditor from './ReminderRuleEditor.vue'

import type { EffectivePolicyValue } from '../../../../../types/index'
import type { RealPolicySettingDefinition } from '../realTypes'
import {
	normalizeReminderPolicyConfig,
	REMINDER_POLICY_DEFAULTS,
	serializeReminderPolicyConfig,
} from './model'

export const reminderRealDefinition: RealPolicySettingDefinition = {
	key: 'reminder_settings',
	title: t('libresign', 'Automatic reminders'),
	description: t('libresign', 'Configure automatic reminder cadence, max attempts, and daily send time.'),
	editor: ReminderRuleEditor,
	resolutionMode: 'precedence',
	createEmptyValue: () => serializeReminderPolicyConfig(REMINDER_POLICY_DEFAULTS),
	normalizeDraftValue: (value: EffectivePolicyValue) => serializeReminderPolicyConfig(normalizeReminderPolicyConfig(value)),
	hasSelectableDraftValue: () => true,
	normalizeAllowChildOverride: (_scope, allowChildOverride: boolean) => allowChildOverride,
	getFallbackSystemDefault: (policyValue: EffectivePolicyValue | null | undefined, sourceScope?: string | null) => {
		if (sourceScope === 'system' && policyValue !== null && policyValue !== undefined) {
			return policyValue
		}

		return serializeReminderPolicyConfig(REMINDER_POLICY_DEFAULTS)
	},
	summarizeValue: (value: EffectivePolicyValue) => {
		const normalized = normalizeReminderPolicyConfig(value)
		if (normalized.days_before <= 0 || normalized.days_between <= 0 || normalized.max <= 0) {
			return t('libresign', 'Disabled')
		}

		return t('libresign', 'Enabled • first after {daysBefore}d • every {daysBetween}d • max {max} • {sendTime}', {
			daysBefore: String(normalized.days_before),
			daysBetween: String(normalized.days_between),
			max: String(normalized.max),
			sendTime: normalized.send_timer || '10:00',
		})
	},
	formatAllowOverride: (allowChildOverride: boolean) =>
		allowChildOverride
			? t('libresign', 'Groups and users can set their own rule')
			: t('libresign', 'Groups and users must follow this value'),
}
