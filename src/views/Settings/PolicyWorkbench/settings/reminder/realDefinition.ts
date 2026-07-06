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
	// TRANSLATORS Policy title for automatic reminder settings sent to signers.
	title: t('libresign', 'Automatic reminders'),
	// TRANSLATORS Policy description covering reminder schedule, maximum attempts, and daily send time.
	description: t('libresign', 'Configure automatic reminder cadence, max attempts, and daily send time.'),
	groupAdminBehavior: {
		allowGroupRuleCreationFromDescendantDelegation: true,
		hideNonRemovableGroupRules: (policy) => policy?.editableByCurrentActor === false && policy?.canSaveAsUserDefault === true,
	},
	editor: ReminderRuleEditor,
	supportedScopes: ['system', 'group', 'user'],
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
			// TRANSLATORS Policy summary meaning automatic reminders are disabled.
			return t('libresign', 'Disabled')
		}

		// TRANSLATORS Policy summary when reminders are enabled. {daysBefore} and {daysBetween} are day counts, the "d" suffix means days, {max} is maximum reminders per signer, and {sendTime} is the daily send time in HH:mm format.
		return t('libresign', 'Enabled • first after {daysBefore}d • every {daysBetween}d • max {max} • {sendTime}', {
			daysBefore: String(normalized.days_before),
			daysBetween: String(normalized.days_between),
			max: String(normalized.max),
			sendTime: normalized.send_timer || '10:00',
		})
	},
	formatAllowOverride: (allowChildOverride: boolean) => {
		if (allowChildOverride) {
			// TRANSLATORS Policy inheritance message indicating group and account scopes may define their own reminder schedule.
			return t('libresign', 'Groups and accounts can set their own rule')
		}

		// TRANSLATORS Policy inheritance message indicating child scopes must use the reminder schedule defined here.
		return t('libresign', 'Groups and accounts must follow this value')
	},
}
