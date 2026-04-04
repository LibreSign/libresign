/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { MailpitClient } from 'mailpit-api'
import { existsSync } from 'node:fs'

export type { MailpitClient }

type Message = Awaited<ReturnType<MailpitClient['getMessageSummary']>>

/** Creates a MailpitClient using MAILPIT_URL (default: http://localhost:8025). */
export function createMailpitClient(): MailpitClient {
	const defaultUrl = existsSync('/.dockerenv')
		? 'http://mailpit:8025'
		: 'http://localhost:8025'
	return new MailpitClient(process.env.MAILPIT_URL ?? defaultUrl)
}

/** Fetches the latest email sent to `toAddress`, optionally filtered by `subject`. */
export async function getLatestEmailTo(
	client: MailpitClient,
	toAddress: string,
	subject?: string,
): Promise<Message> {
	const query = subject
		? `to:${toAddress} subject:"${subject}"`
		: `to:${toAddress}`
	const result = await client.searchMessages({ query })
	if (!result.messages || result.messages.length === 0) {
		throw new Error(`No email found for "${toAddress}"${subject ? ` with subject "${subject}"` : ''}`)
	}
	return await client.getMessageSummary(result.messages[0].ID)
}

/**
 * Polls MailPit until an email matching `toAddress` (and optional `subject`) is found,
 * or until `timeout` ms elapse. Checks every `interval` ms (defaults: 30 s / 1 s).
 */
export async function waitForEmailTo(
	client: MailpitClient,
	toAddress: string,
	subject?: string,
	options?: { timeout?: number; interval?: number },
): Promise<Message> {
	const timeout = options?.timeout ?? 30_000
	const interval = options?.interval ?? 1_000
	const deadline = Date.now() + timeout
	while (Date.now() < deadline) {
		try {
			return await getLatestEmailTo(client, toAddress, subject)
		} catch {
			// email not arrived yet
		}
		await new Promise(resolve => setTimeout(resolve, interval))
	}
	throw new Error(
		`Timeout (${timeout} ms) waiting for email to "${toAddress}"${
			subject ? ` with subject "${subject}"` : ''
		}`,
	)
}

/** Extracts a LibreSign sign link from an email body matching /p/sign/{uuid}. */
export function extractSignLink(body: string): string | null {
	const match = body.match(/\/p\/sign\/[\w-]+(?:\/pdf)?/i)
	return match ? match[0] : null
}

/** Extracts a numeric token from an email body. Default pattern: 4-8 digit sequence. */
export function extractTokenFromEmail(
	body: string,
	pattern: RegExp = /Use this code to sign the document:[\s\S]*?(\d{6})/,
): string | null {
	const match = body.match(pattern)
	return match ? match[1] : null
}

/** Extracts the first URL from an email body (email.Text). */
export function extractLinkFromEmail(body: string): string | null {
	const match = body.match(/https?:\/\/\S+/)
	return match ? match[0] : null
}
