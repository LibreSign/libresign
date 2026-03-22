/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Component } from 'vue'

export type AdminViewMode = 'system-admin' | 'group-admin'
export type PolicyScope = 'system' | 'group' | 'user'
export type PolicySettingKey =
	| 'signature_flow'
	| 'confetti'
	| 'signature_stamp'
	| 'identify_factors'
	| 'auto_reminders'
	| 'request_notifications'
	| 'document_download_after_sign'
export type SignatureFlowMode = 'parallel' | 'ordered_numeric'
export type SignatureStampRenderMode = 'DESCRIPTION_ONLY' | 'GRAPHIC_AND_DESCRIPTION' | 'SIGNAME_AND_DESCRIPTION' | 'GRAPHIC_ONLY'
export type SignatureStampBackgroundMode = 'default' | 'custom' | 'none'
export type IdentifyFactorKey = 'email' | 'sms' | 'whatsapp' | 'document'
export type IdentifyFactorSignatureMethod = 'email_token' | 'sms_token' | 'whatsapp_token' | 'document_validation'

export type SignatureFlowRuleValue = {
	enabled: boolean
	flow: SignatureFlowMode
}

export type ConfettiRuleValue = {
	enabled: boolean
}

export type SignatureStampRuleValue = {
	enabled: boolean
	renderMode: SignatureStampRenderMode
	template: string
	templateFontSize: number
	signatureFontSize: number
	signatureWidth: number
	signatureHeight: number
	backgroundMode: SignatureStampBackgroundMode
	showSigningDate: boolean
}

export type IdentifyFactorOption = {
	key: IdentifyFactorKey
	label: string
	enabled: boolean
	required: boolean
	allowCreateAccount: boolean
	signatureMethod: IdentifyFactorSignatureMethod
}

export type IdentifyFactorsRuleValue = {
	enabled: boolean
	requireAnyTwo: boolean
	factors: IdentifyFactorOption[]
}

export type PolicySettingValueMap = {
	signature_flow: SignatureFlowRuleValue
	confetti: ConfettiRuleValue
	signature_stamp: SignatureStampRuleValue
	identify_factors: IdentifyFactorsRuleValue
	auto_reminders: ConfettiRuleValue
	request_notifications: ConfettiRuleValue
	document_download_after_sign: ConfettiRuleValue
}

export type PolicyRuleRecord<K extends PolicySettingKey = PolicySettingKey> = {
	id: string
	scope: PolicyScope
	targetId: string | null
	allowChildOverride: boolean
	value: PolicySettingValueMap[K]
}

export type PolicyEditorDraft = {
	id: string | null
	settingKey: PolicySettingKey
	scope: PolicyScope
	targetId: string | null
	allowChildOverride: boolean
	value: PolicySettingValueMap[PolicySettingKey]
}

export type PolicyTargetOption = {
	id: string
	label: string
	groupId?: string
}

export type PolicySettingDefinition<K extends PolicySettingKey = PolicySettingKey> = {
	key: K
	title: string
	description: string
	menuHint: string
	editor: Component
	createEmptyValue: (scope: PolicyScope) => PolicySettingValueMap[K]
	summarizeValue: (value: PolicySettingValueMap[K]) => string
	formatAllowOverride: (allowChildOverride: boolean) => string | null
}

export type PolicySettingSummary = {
	key: PolicySettingKey
	title: string
	description: string
	menuHint: string
	defaultSummary: string
	groupCount: number
	userCount: number
}
