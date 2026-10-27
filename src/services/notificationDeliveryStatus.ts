/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type { components, operations } from '../types/openapi/openapi.js'

export type NotificationStatus = components['schemas']['NotificationStatusResponse']

export interface NotificationStatusQuery {
	fileId: number
	signRequestId: number
	requestId: string
}

interface WatchOptions {
	signal: AbortSignal
	onUpdate: (status: NotificationStatus) => void
	intervalMs?: number
	maxChecks?: number
}

export type NotificationSubmitResult =
	| { kind: 'accepted' | 'not_found' | 'status_unavailable'; message?: string }
	| { kind: 'unconfirmed' }
	| { kind: 'invalid_request'; error: unknown }

type NotifySignerSuccess = operations['notify-signer']['responses'][200]['content']['application/json']

const STATUS_URL = '/apps/libresign/api/v1/notify/status'
const SUBMIT_URL = '/apps/libresign/api/v1/notify/signer'

export function newNotificationRequestId(): string {
	const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16))
	return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('')
}

export async function fetchNotificationStatus(query: NotificationStatusQuery, signal?: AbortSignal): Promise<NotificationStatus> {
	const response = await axios.get<{ ocs: { data: NotificationStatus } }>(
		generateOcsUrl(STATUS_URL),
		{ params: query, signal },
	)
	const status = response.data.ocs.data
	if (typeof status?.complete !== 'boolean' || !Array.isArray(status.channels)) {
		throw new Error('Invalid notification status response')
	}
	return status
}

function sleepUnlessCanceled(ms: number, signal: AbortSignal): Promise<void> {
	if (signal.aborted) {
		return Promise.resolve()
	}
	return new Promise(resolve => {
		const onDone = () => {
			clearTimeout(timer)
			signal.removeEventListener('abort', onDone)
			resolve()
		}
		const timer = setTimeout(onDone, ms)
		signal.addEventListener('abort', onDone, { once: true })
	})
}

/** Only poll while the backend reports work in progress. */
export async function watchNotificationStatus(
	query: NotificationStatusQuery,
	options: WatchOptions,
): Promise<NotificationStatus | null> {
	const { signal, onUpdate, intervalMs = 1500, maxChecks = 40 } = options
	for (let attempt = 0; attempt < maxChecks && !signal.aborted; attempt++) {
		const status = await fetchNotificationStatus(query, signal)
		if (signal.aborted) {
			break
		}
		onUpdate(status)
		if (status.complete) {
			return status
		}
		if (attempt + 1 < maxChecks) {
			await sleepUnlessCanceled(intervalMs, signal)
		}
	}
	return null
}

function isRejectedRequest(error: unknown): boolean {
	if (typeof error !== 'object' || error === null || !('response' in error)) {
		return false
	}
	const response = error.response
	if (typeof response !== 'object' || response === null || !('status' in response)) {
		return false
	}
	return typeof response.status === 'number' && response.status >= 400 && response.status < 500
}

/**
 * The request ID identifies one explicit click across an HTTP timeout.
 * Reading status cannot cause another submission. A later click uses a new ID.
 */
export async function submitAndWatchNotification(
	query: NotificationStatusQuery,
	options: WatchOptions,
): Promise<NotificationSubmitResult> {
	let message: string | undefined
	let submissionFailed = false
	try {
		const response = await axios.post<NotifySignerSuccess>(generateOcsUrl(SUBMIT_URL), query)
		message = response.data.ocs.data.message
	} catch (error: unknown) {
		if (isRejectedRequest(error)) {
			return { kind: 'invalid_request', error }
		}
		submissionFailed = true
	}

	if (options.signal.aborted) {
		return { kind: 'unconfirmed' }
	}
	try {
		const status = await watchNotificationStatus(query, options)
		if (status?.status === 'completed') {
			return { kind: 'accepted', message }
		}
		if (status?.status === 'not_found' && !submissionFailed) {
			return { kind: 'not_found', message }
		}
		return { kind: 'unconfirmed' }
	} catch {
		return submissionFailed ? { kind: 'unconfirmed' } : { kind: 'status_unavailable', message }
	}
}
