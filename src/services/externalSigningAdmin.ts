/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/** Read-only driver catalog contract. Do not extend this with speculative fields. */
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

export interface ExternalSigningScope {
    scopeType: 'system' | 'group' | 'user'
    scopeId: string
}

/** Frontend instance model; persistence must be supplied by the administrative adapter. */
export interface ExternalSigningInstance {
	id: string
	driverId: string
	name: string
	enabled: boolean
	configuration: Record<string, string>
	configuredSecretFields: string[]
	revision: number
	scopeType?: ExternalSigningScope['scopeType']
	scopeId?: string
	hasSecrets?: boolean
	driverAvailable?: boolean
}

export interface ExternalSigningDraft {
	id?: string
	scopeType?: ExternalSigningScope['scopeType']
	scopeId?: string
	driverId: string
	name: string
	configuration: Record<string, string>
	secretReplacements: Record<string, string | null>
	revision?: number
}

export interface ExternalSigningAdminAdapter {
	list(): Promise<ExternalSigningInstance[]>
	get?(instance: ExternalSigningInstance): Promise<ExternalSigningInstance>
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
		if (field.secret && value && value.length > 8192) {
			errors[field.key] = 'invalid'
		}
		if (value && field.type === 'url') {
			try {
				const url = new URL(value)
				const host = url.hostname.toLowerCase().replace(/\.$/, '')
				const unsafeHost = !host.includes('.')
					|| host.includes('[') || host.includes(']') || host.includes(':')
					|| /^[0-9.]+$/.test(host)
					|| /\.(local|localhost|internal)$/.test(host)
					|| host.split('.').some(label => !/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/.test(label))
				if ((field.validation.schemes?.length && !field.validation.schemes.includes(url.protocol.slice(0, -1)))
					|| (!field.validation.allowUserInfo && (url.username || url.password))
					|| value.length > 2048 || Array.from(value).some(char => char.charCodeAt(0) <= 32 || char.charCodeAt(0) === 127)
					|| Boolean(url.search || url.hash) || unsafeHost) {
					errors[field.key] = 'invalid'
				}
			} catch {
				errors[field.key] = 'invalid'
			}
		}
	}
	return errors
}

/** The server enforces administrator access and the experimental feature gate. */
export async function fetchExternalSigningDrivers(): Promise<ExternalSigningDriver[]> {
	const response = await axios.get<{ ocs?: { data?: { drivers?: ExternalSigningDriver[] } } }>(generateOcsUrl('/apps/libresign/api/v1/admin/signing/drivers'))
	const drivers = response.data?.ocs?.data?.drivers
	if (!Array.isArray(drivers)) {
		throw new Error('Invalid external signing catalog response')
	}
	return drivers
}
