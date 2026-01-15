/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export const waitForFileStatusChange = async (fileId, currentStatus, timeout = 30) => {
	const url = generateOcsUrl('/apps/libresign/api/v1/file/{fileId}/wait-status', { fileId })

	const response = await axios.get(url, {
		params: {
			currentStatus,
			timeout,
		},
		timeout: (timeout + 5) * 1000,
	})

	return response.data.ocs.data
}

export const startLongPolling = (fileId, initialStatus, onUpdate, shouldStop, onError = null) => {
	let isRunning = true
	let currentStatus = initialStatus

	const stopPolling = () => {
		isRunning = false
	}

	const poll = async () => {
		while (isRunning) {
			if (shouldStop && shouldStop()) {
				break
			}

			try {
				const data = await waitForFileStatusChange(fileId, currentStatus, 30)

				if (data.status !== currentStatus) {
					currentStatus = data.status
					onUpdate(data)

					if (isTerminalStatus(data.status)) {
						break
					}
				}
			} catch (error) {
				if (onError) {
					onError(error)
				}

				await sleep(3000)
			}
		}
	}

	poll()

	return stopPolling
}

const isTerminalStatus = (status) => {
	const TERMINAL_STATUSES = [3, 4, 1]
	return TERMINAL_STATUSES.includes(status)
}

const sleep = (ms) => {
	return new Promise(resolve => setTimeout(resolve, ms))
}
