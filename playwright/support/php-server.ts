/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { readFileSync } from 'node:fs'

function readTrimmed(path: string): string | null {
	try {
		return readFileSync(path, 'utf8').trim()
	} catch {
		// The workflow writes the exit file only after the server stopped.
		return null
	}
}

function isRunning(pid: number): boolean {
	try {
		process.kill(pid, 0)
		return true
	} catch (error) {
		// EPERM: the process exists but belongs to another user.
		return (error as NodeJS.ErrnoException).code === 'EPERM'
	}
}

/**
 * Fails fast with the exit status of the PHP built-in server started by CI instead of ECONNREFUSED.
 * Assumes Playwright and `php -S` share a PID namespace, as in the CI job container.
 */
export function assertPhpServerRunning(): void {
	const pidFile = process.env.LIBRESIGN_PHP_SERVER_PID_FILE
	const exitFile = process.env.LIBRESIGN_PHP_SERVER_EXIT_FILE
	if (!pidFile || !exitFile) {
		return
	}
	const exitStatus = readTrimmed(exitFile)
	if (exitStatus) {
		throw new Error(`PHP server exited (${exitStatus})`)
	}
	const pid = Number(readTrimmed(pidFile))
	if (!isRunning(pid)) {
		throw new Error(`PHP server exited (PID ${pid} is gone, no exit status recorded)`)
	}
}
