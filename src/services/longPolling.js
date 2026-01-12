/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Wait for file status to change from current status (long polling)
 *
 * Keeps connection open for up to 30 seconds waiting for status change.
 * If status changes, returns immediately with new status.
 *
 * @param {number} fileId LibreSign file ID
 * @param {number} currentStatus Current status known by client
 * @param {number} timeout Timeout in seconds (default 30)
 * @return {Promise<{status: number, statusText: string, name: string, progress: object}>}
 */
export const waitForFileStatusChange = async (fileId, currentStatus, timeout = 30) => {
	const url = generateOcsUrl('/apps/libresign/api/v1/file/{fileId}/wait-status', { fileId })

	const response = await axios.get(url, {
		params: {
			currentStatus,
			timeout,
		},
		timeout: (timeout + 5) * 1000, // Add 5 seconds buffer to HTTP timeout
	})

	return response.data.ocs.data
}

/**
 * Start long polling loop for file status updates
 *
 * Continuously polls for status changes and calls onUpdate callback
 * when status changes. Stops when shouldStop callback returns true
 * or when status reaches a terminal state (SIGNED, DELETED, ABLE_TO_SIGN).
 *
 * @param {number} fileId LibreSign file ID
 * @param {number} initialStatus Initial status to watch from
 * @param {Function} onUpdate Callback called with updated data: (data) => void
 * @param {Function} shouldStop Callback to check if should stop: () => boolean
 * @param {Function} onError Optional callback for errors: (error) => void
 * @return {Function} Stop function to cancel polling
 */
export const startLongPolling = (fileId, initialStatus, onUpdate, shouldStop, onError = null) => {
	let isRunning = true
	let currentStatus = initialStatus

	const stopPolling = () => {
		isRunning = false
	}

	const poll = async () => {
		while (isRunning) {
			// Check if we should stop
			if (shouldStop && shouldStop()) {
				break
			}

			try {
				const data = await waitForFileStatusChange(fileId, currentStatus, 30)

				// Status changed
				if (data.status !== currentStatus) {
					currentStatus = data.status
					onUpdate(data)

					// Stop polling if reached terminal status
					if (isTerminalStatus(data.status)) {
						break
					}
				}
			} catch (error) {
				if (onError) {
					onError(error)
				}

				// On error, wait 3 seconds before retrying
				await sleep(3000)
			}
		}
	}

	// Start polling loop
	poll()

	return stopPolling
}

/**
 * Check if status is terminal (no more changes expected)
 *
 * @param {number} status File status
 * @return {boolean}
 */
const isTerminalStatus = (status) => {
	const TERMINAL_STATUSES = [
		3, // STATUS_SIGNED
		4, // STATUS_DELETED
		1, // STATUS_ABLE_TO_SIGN (after error or completion)
	]
	return TERMINAL_STATUSES.includes(status)
}

/**
 * Sleep for specified milliseconds
 *
 * @param {number} ms Milliseconds to sleep
 * @return {Promise<void>}
 */
const sleep = (ms) => {
	return new Promise(resolve => setTimeout(resolve, ms))
}
