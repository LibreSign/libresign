/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'

import {
	createAuthenticatedRequestContext,
	policyRequest,
} from '../support/policy-api'

const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

test.describe.configure({ mode: 'serial', retries: 0 })

test('rejects unsupported signature_flow value at system scope', async () => {
	const ctx = await createAuthenticatedRequestContext(adminUser, adminPassword)
	try {
		const response = await policyRequest(
			ctx,
			'POST',
			'/apps/libresign/api/v1/policies/system/signature_flow',
			{ value: 'invalid_flow_mode', allowChildOverride: true },
		)

		expect(response.httpStatus).not.toBe(200)
		expect([400, 422]).toContain(response.httpStatus)
	} finally {
		await ctx.dispose()
	}
})

test('rejects unsupported signature_flow value at group scope', async () => {
	const ctx = await createAuthenticatedRequestContext(adminUser, adminPassword)
	try {
		const response = await policyRequest(
			ctx,
			'PUT',
			'/apps/libresign/api/v1/policies/group/admin/signature_flow',
			{ value: 'invalid_flow_mode', allowChildOverride: true },
		)

		expect(response.httpStatus).not.toBe(200)
		expect([400, 422]).toContain(response.httpStatus)
	} finally {
		await ctx.dispose()
	}
})
