/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios, { type AxiosResponse } from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

interface FileStatusData {
	status: number
	[key: string]: unknown
}

interface LongPollingOptions {
	waitForFileStatusChange?: (fileId: number, currentStatus: number, timeout?: number) => Promise<FileStatusData>
	sleep?: (ms: number) => Promise<void>
}

export const waitForFileStatusChange = async (fileId: number, currentStatus: number, timeout: number = 30): Promise<FileStatusData> => {
	const url = generateOcsUrl('/apps/libresign/api/v1/file/{fileId}/wait-status', { fileId })

	const response: AxiosResponse<{ ocs: { data: FileStatusData } }> = await axios.get(url, {
		params: {
			currentStatus,
			timeout,
		},
		timeout: (timeout + 5) * 1000,
	})

	return response.data.ocs.data
}

export const createLongPolling = (options: LongPollingOptions = {}) => {
	return (
		fileId: number,
		initialStatus: number,
		onUpdate: (data: FileStatusData) => void,
		shouldStop: (() => boolean) | null,
		onError: ((error: unknown) => void) | null = null,
	): (() => void) => {
		let isRunning = true
		let currentStatus = initialStatus
		let errorCount = 0
		const MAX_ERRORS = 5
		const waitForStatusChange = options.waitForFileStatusChange || waitForFileStatusChange
		const sleepFn = options.sleep || sleep

		const stopPolling = (): void => {
			isRunning = false
		}

		const poll = async (): Promise<void> => {
			while (isRunning) {
				if (shouldStop && shouldStop()) {
					break
				}

				try {
					const data = await waitForStatusChange(fileId, currentStatus, 30)

					errorCount = 0

					if (data.status !== currentStatus) {
						currentStatus = data.status
						onUpdate(data)

						if (isTerminalStatus(data.status)) {
							break
						}
					}
				} catch (error) {
					errorCount++

					if (onError) {
						onError(error)
					}

					if (errorCount >= MAX_ERRORS) {
						console.error('Long polling stopped after', MAX_ERRORS, 'consecutive errors')
						break
					}

					await sleepFn(3000)
				}
			}
		}

		poll()

		return stopPolling
	}
}

export const startLongPolling = (
	fileId: number,
	initialStatus: number,
	onUpdate: (data: FileStatusData) => void,
	shouldStop: (() => boolean) | null,
	onError: ((error: unknown) => void) | null = null,
	options: LongPollingOptions = {},
): (() => void) => {
	return createLongPolling(options)(fileId, initialStatus, onUpdate, shouldStop, onError)
}

const isTerminalStatus = (status: number): boolean => {
	const TERMINAL_STATUSES = [3, 4, 1]
	return TERMINAL_STATUSES.includes(status)
}

const sleep = (ms: number): Promise<void> => {
	return new Promise(resolve => setTimeout(resolve, ms))
}
