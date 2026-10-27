/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from '@nextcloud/axios'
import {
	newNotificationRequestId,
	submitAndWatchNotification,
	watchNotificationStatus,
} from '../../services/notificationDeliveryStatus'
import type { NotificationStatus } from '../../services/notificationDeliveryStatus'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((url: string) => '/ocs' + url),
}))

const query = { fileId: 12, signRequestId: 23, requestId: 'a'.repeat(32) }

const accepted: NotificationStatus = {
	status: 'completed',
	complete: true,
	channels: [{ channel: 'mail', state: 'accepted' }],
}
const pending: NotificationStatus = {
	status: 'processing',
	complete: false,
	channels: [{ channel: 'mail', state: 'sending' }],
}
const unknown: NotificationStatus = {
	status: 'attention_required',
	complete: true,
	channels: [{ channel: 'mail', state: 'unknown' }],
}
const response = (status: NotificationStatus) => ({ data: { ocs: { data: status } } })

describe('Notification delivery status', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.mocked(axios.get).mockReset()
		vi.mocked(axios.post).mockReset()
	})

	it('generates a cryptographically random 128-bit request key', () => {
		const a = newNotificationRequestId()
		const b = newNotificationRequestId()
		expect(a).toMatch(/^[a-f0-9]{32}$/)
		expect(b).not.toEqual(a)
	})

	it('reads each channel and stops polling on a completed result', async () => {
		vi.mocked(axios.get)
			.mockResolvedValueOnce(response(pending))
			.mockResolvedValueOnce(response(accepted))
		const onUpdate = vi.fn()
		const result = await watchNotificationStatus(query, {
			signal: new AbortController().signal, onUpdate, intervalMs: 0, maxChecks: 5,
		})
		expect(result).toEqual(accepted)
		expect(onUpdate).toHaveBeenCalledTimes(2)
		expect(vi.mocked(axios.get)).toHaveBeenCalledTimes(2)
	})

	it('stops without a new request when the component cancels tracking', async () => {
		const controller = new AbortController()
		vi.mocked(axios.get).mockResolvedValue(response(pending))
		const result = await watchNotificationStatus(query, {
			signal: controller.signal,
			onUpdate: () => controller.abort(),
			intervalMs: 0,
		})
		expect(result).toBeNull()
		expect(vi.mocked(axios.get)).toHaveBeenCalledTimes(1)
	})

	it('limits calls if the server never reports a terminal state', async () => {
		vi.mocked(axios.get).mockResolvedValue(response(pending))
		const result = await watchNotificationStatus(query, {
			signal: new AbortController().signal, onUpdate: vi.fn(), intervalMs: 0, maxChecks: 2,
		})
		expect(result).toBeNull()
		expect(vi.mocked(axios.get)).toHaveBeenCalledTimes(2)
	})

	it('never re-sends after losing an HTTP response when the ledger shows acceptance', async () => {
		vi.mocked(axios.post).mockRejectedValueOnce(new Error('Response lost after SMTP DATA'))
		vi.mocked(axios.get).mockResolvedValueOnce(response(accepted))
		const result = await submitAndWatchNotification(query, {
			signal: new AbortController().signal, onUpdate: vi.fn(),
		})
		expect(result.kind).toBe('unconfirmed')
		expect(vi.mocked(axios.get)).toHaveBeenCalledTimes(1)
		expect(vi.mocked(axios.post)).toHaveBeenCalledTimes(1)
		expect(vi.mocked(axios.post)).toHaveBeenCalledWith(
			'/ocs/apps/libresign/api/v1/notify/signer', query,
		)
	})

	it('reports an indeterminate provider outcome without retrying the POST', async () => {
		vi.mocked(axios.post).mockRejectedValueOnce(new Error('Unknown transport result'))
		vi.mocked(axios.get).mockResolvedValueOnce(response(unknown))
		const result = await submitAndWatchNotification(query, {
			signal: new AbortController().signal, onUpdate: vi.fn(),
		})
		expect(result.kind).toBe('unconfirmed')
		expect(vi.mocked(axios.post)).toHaveBeenCalledTimes(1)
	})

	it('does not query status after a rejected validation request', async () => {
		vi.mocked(axios.post).mockRejectedValueOnce({ response: { status: 422 } })
		const result = await submitAndWatchNotification(query, {
			signal: new AbortController().signal, onUpdate: vi.fn(),
		})
		expect(result.kind).toBe('invalid_request')
		expect(vi.mocked(axios.get)).not.toHaveBeenCalled()
	})

	it('preserves synchronous success when the optional status query fails', async () => {
		vi.mocked(axios.post).mockResolvedValueOnce({
			data: { ocs: { data: { message: 'Request processed' } } },
		})
		vi.mocked(axios.get).mockRejectedValueOnce(new Error('Status endpoint unavailable'))
		const result = await submitAndWatchNotification(query, {
			signal: new AbortController().signal, onUpdate: vi.fn(),
		})
		expect(result).toEqual({ kind: 'status_unavailable', message: 'Request processed' })
	})
})
