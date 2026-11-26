/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import type { ExternalSigningAdminAdapter, ExternalSigningDraft, ExternalSigningInstance, ExternalSigningScope } from './externalSigningAdmin'

const ROOT = '/apps/libresign/api/v1/admin/signing/integrations'
const PAGE_SIZE = 50
const MAX_PAGES = 20

type OcsResponse<T> = { ocs?: { data?: T } }

interface IntegrationSummary {
	id: string
	driverId: string
	name: string
	configuration: Record<string, string>
	enabled: boolean
	hasSecrets: boolean
	revision: number
	scopeType: ExternalSigningScope['scopeType']
	scopeId: string
	driverAvailable: boolean
}

interface IntegrationDetail extends IntegrationSummary {
	secretFields: { key: string, isConfigured: boolean }[]
}

interface IntegrationPage {
	items: IntegrationSummary[]
	nextCursor: number | null
}

export class ExternalSigningAdminError extends Error {
	constructor(public readonly status: number | null) {
		super('External signing administration request failed')
	}
}

async function unwrap<T>(request: Promise<{ data: OcsResponse<T> }>): Promise<T> {
	try {
		const response = await request
		const payload = response.data?.ocs?.data
		if (!payload || typeof payload !== 'object') {
			throw new Error('Invalid administrative response')
		}
		return payload
	} catch (error) {
		const status = (error as { response?: { status?: unknown } })?.response?.status
		throw new ExternalSigningAdminError(typeof status === 'number' ? status : null)
	}
}

function integrationUrl(id?: string): string {
	if (!id) {
		return generateOcsUrl(ROOT)
	}
	if (!/^[a-f0-9]{32}$/.test(id)) {
		throw new Error('Invalid external integration identifier')
	}
	return generateOcsUrl(ROOT + '/{id}', { id })
}

function toModel(row: IntegrationSummary, detail?: IntegrationDetail): ExternalSigningInstance {
	if (!row || typeof row.id !== 'string' || typeof row.driverId !== 'string'
		|| !Number.isSafeInteger(row.revision) || row.revision < 1
		|| !row.configuration || typeof row.configuration !== 'object' || Array.isArray(row.configuration)) {
		throw new Error('Invalid external integration metadata')
	}
	const configuration: Record<string, string> = {}
	for (const [key, value] of Object.entries(row.configuration)) {
		if (typeof value !== 'string') {
			throw new Error('Invalid external integration configuration')
		}
		configuration[key] = value
	}
	const fields = detail?.secretFields ?? []
	if (!Array.isArray(fields)) {
		throw new Error('Invalid external integration secret status')
	}
	return {
		id: row.id,
		driverId: row.driverId,
		name: row.name,
		configuration,
		enabled: row.enabled,
		hasSecrets: row.hasSecrets,
		driverAvailable: row.driverAvailable,
		revision: row.revision,
		scopeType: row.scopeType,
		scopeId: row.scopeId,
		configuredSecretFields: fields.filter(field => field.isConfigured === true && typeof field.key === 'string').map(field => field.key),
	}
}

async function getDetail(id: string): Promise<ExternalSigningInstance> {
	const detail = await unwrap<IntegrationDetail>(axios.get<OcsResponse<IntegrationDetail>>(integrationUrl(id)))
	return toModel(detail, detail)
}

function secretChanges(draft: ExternalSigningDraft): Record<string, string | null> {
	return Object.fromEntries(Object.entries(draft.secretReplacements)
		.filter(([, value]) => value === null || (typeof value === 'string' && value.length > 0)))
}

export function createExternalSigningAdminOcsAdapter(scope: ExternalSigningScope = { scopeType: 'system', scopeId: '' }): ExternalSigningAdminAdapter {
	const scopeId = scope.scopeId.trim()
	if ((scope.scopeType === 'system' && scopeId !== '')
		|| ((scope.scopeType === 'group' || scope.scopeType === 'user') && !scopeId)
		|| !['system', 'group', 'user'].includes(scope.scopeType)
		|| scopeId.length > 255) {
		throw new Error('Invalid external integration policy scope')
	}
	const scopeParams = { scopeType: scope.scopeType, scopeId }
	const assertWithinScope = (instance: ExternalSigningInstance): ExternalSigningInstance => {
		if (instance.scopeType !== scope.scopeType || instance.scopeId !== scopeId) {
			throw new Error('Integration belongs to another policy scope')
		}
		return instance
	}
	return {
		async list() {
			const results: ExternalSigningInstance[] = []
			let cursor = 0
			for (let pageIndex = 0; pageIndex < MAX_PAGES; pageIndex++) {
				const page = await unwrap<IntegrationPage>(axios.get<OcsResponse<IntegrationPage>>(integrationUrl(), {
					params: { ...scopeParams, afterId: cursor, pageSize: PAGE_SIZE },
				}))
				if (!Array.isArray(page.items)) {
					throw new Error('Invalid external integration list response')
				}
				results.push(...page.items.map(row => assertWithinScope(toModel(row))))
				if (page.nextCursor === null) {
					return results
				}
				if (!Number.isSafeInteger(page.nextCursor) || page.nextCursor <= cursor) {
					throw new Error('Invalid external integration page cursor')
				}
				cursor = page.nextCursor
			}
			throw new Error('External integration list exceeds the supported page limit')
		},
		get: async instance => assertWithinScope(await getDetail(assertWithinScope(instance).id)),
		async save(draft) {
			const secrets = secretChanges(draft)
			if (draft.id) {
				if (draft.scopeType !== undefined && (draft.scopeType !== scope.scopeType || draft.scopeId !== scopeId)) {
					throw new Error('Integration belongs to another policy scope')
				}
				if (!Number.isSafeInteger(draft.revision) || !draft.revision || draft.revision < 1) {
					throw new Error('External integration revision is required')
				}
				const data = {
					revision: draft.revision,
					name: draft.name,
					configuration: draft.configuration,
					...(Object.keys(secrets).length ? { secrets } : {}),
				}
				const detail = await unwrap<IntegrationDetail>(axios.patch<OcsResponse<IntegrationDetail>>(integrationUrl(draft.id), data))
				return assertWithinScope(toModel(detail, detail))
			}
			const data = {
				...scopeParams,
				driverId: draft.driverId,
				name: draft.name,
				configuration: draft.configuration,
				secrets,
			}
			const detail = await unwrap<IntegrationDetail>(axios.post<OcsResponse<IntegrationDetail>>(integrationUrl(), data))
			return assertWithinScope(toModel(detail, detail))
		},
		async setEnabled(instance, enabled) {
			assertWithinScope(instance)
			const url = integrationUrl(instance.id) + (enabled ? '/enable' : '/disable')
			const detail = await unwrap<IntegrationDetail>(axios.post<OcsResponse<IntegrationDetail>>(url, { revision: instance.revision }))
			return assertWithinScope(toModel(detail, detail))
		},
		async remove(instance) {
			assertWithinScope(instance)
			const result = await unwrap<{ deleted: boolean }>(axios.delete<OcsResponse<{ deleted: boolean }>>(integrationUrl(instance.id), {
				params: { revision: instance.revision },
			}))
			if (result.deleted !== true) {
				throw new Error('External integration deletion was not confirmed')
			}
		},
	}
}
