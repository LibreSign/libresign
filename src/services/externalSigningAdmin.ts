/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/** Read-only catalog contract from PR #9611. Do not extend this with speculative fields. */
export interface ExternalSigningField {
	key: string
	label: string
	type: 'url' | 'password'
	required: boolean
	secret: boolean
	validation: { schemes?: string[], allowUserInfo?: boolean }
}

export interface ExternalSigningDriver {
	id: string
	name: string
	description: string
	experimental: boolean
	available: boolean
	unavailableReason: string | null
	fields: ExternalSigningField[]
}

/** UI-only contract, pending final #9596 API. NOT a deployed endpoint. */
export interface ExternalSigningInstance {
	id: string
	driverId: string
	name: string
	enabled: boolean
	configuration: Record<string, string>
	configuredSecretFields: string[]
	revision: number
	hasActiveSessions?: boolean
}

export interface ExternalSigningDraft {
	id?: string
	driverId: string
	name: string
	configuration: Record<string, string>
	secretReplacements: Record<string, string>
	revision?: number
}

export interface ExternalSigningAdminAdapter {
	list(): Promise<ExternalSigningInstance[]>
	save(draft: ExternalSigningDraft): Promise<ExternalSigningInstance>
	setEnabled(instance: ExternalSigningInstance, enabled: boolean): Promise<ExternalSigningInstance>
	remove(instance: ExternalSigningInstance): Promise<void>
}

export function validateExternalSigningDraft(driver: ExternalSigningDriver, draft: ExternalSigningDraft, existing?: ExternalSigningInstance): Record<string, string> {
	const errors: Record<string, string> = {}
	if (!draft.name.trim()) {
		errors.name = 'required'
	}
	for (const field of driver.fields) {
		const value = field.secret ? draft.secretReplacements[field.key] : draft.configuration[field.key]
		const alreadyConfigured = field.secret && existing?.configuredSecretFields.includes(field.key)
		if (field.required && !value?.trim() && !alreadyConfigured) {
			errors[field.key] = 'required'
			continue
		}
		if (value && field.type === 'url') {
			try {
				const url = new URL(value)
				if ((field.validation.schemes?.length && !field.validation.schemes.includes(url.protocol.slice(0, -1))) || (!field.validation.allowUserInfo && (url.username || url.password))) {
					errors[field.key] = 'invalid'
				}
			} catch {
				errors[field.key] = 'invalid'
			}
		}
	}
	return errors
}

/** #9611 only: returns 401/403/404 when unauthorized or experimental gate is off. */
export async function fetchExternalSigningDrivers(): Promise<ExternalSigningDriver[]> {
	const response = await axios.get<{ ocs?: { data?: { drivers?: ExternalSigningDriver[] } } }>(generateOcsUrl('/apps/libresign/api/v1/admin/signing/drivers'))
	const drivers = response.data?.ocs?.data?.drivers
	if (!Array.isArray(drivers)) {
		throw new Error('Invalid external signing catalog response')
	}
	return drivers
}
