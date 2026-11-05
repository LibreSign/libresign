/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import {
	composeSignatureRejectionValue,
	decomposeSignatureRejectionValue,
	DEFAULT_SIGNATURE_REJECTION,
	normalizeSignatureRejectionDraftValue,
	summarizeSignatureRejectionDraftValue,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-rejection/model'

const tFn = (_app: string, text: string, vars?: Record<string, string>) =>
	text.replace(/\{(\w+)\}/g, (_match, key: string) => vars?.[key] ?? `{${key}}`)

const configured = {
	enabled: true,
	behavior: 'continue',
	commentMode: 'required',
	visibility: 'participants',
	commentVisibility: 'requester',
} as const

describe('signature rejection model', () => {
	it('defaults to the backend defaults', () => {
		expect(DEFAULT_SIGNATURE_REJECTION).toEqual({
			enabled: false,
			behavior: 'cancel',
			commentMode: 'disabled',
			visibility: 'requester',
			commentVisibility: 'requester',
		})
		expect(normalizeSignatureRejectionDraftValue(null)).toEqual(DEFAULT_SIGNATURE_REJECTION)
		expect(composeSignatureRejectionValue({})).toEqual(DEFAULT_SIGNATURE_REJECTION)
	})

	it('reads a scalar parent value as the enabled flag', () => {
		expect(normalizeSignatureRejectionDraftValue(true)).toEqual({ ...DEFAULT_SIGNATURE_REJECTION, enabled: true })
		expect(normalizeSignatureRejectionDraftValue('1')).toEqual({ ...DEFAULT_SIGNATURE_REJECTION, enabled: true })
		expect(normalizeSignatureRejectionDraftValue(0)).toEqual(DEFAULT_SIGNATURE_REJECTION)
	})

	it('falls back per field when a value is unknown', () => {
		expect(normalizeSignatureRejectionDraftValue({
			enabled: true,
			behavior: 'archive',
			commentMode: 'maybe',
			visibility: 'everyone',
			commentVisibility: 42,
		})).toEqual({ ...DEFAULT_SIGNATURE_REJECTION, enabled: true })
	})

	it('composes the five backend keys into one editor value', () => {
		expect(composeSignatureRejectionValue({
			rejection_enabled: true,
			rejection_behavior: 'continue',
			rejection_comment_mode: 'required',
			rejection_visibility: 'participants',
			rejection_comment_visibility: 'requester',
		})).toEqual(configured)
	})

	it('decomposes the editor value into the five backend keys', () => {
		expect(decomposeSignatureRejectionValue(configured)).toEqual({
			rejection_enabled: true,
			rejection_behavior: 'continue',
			rejection_comment_mode: 'required',
			rejection_visibility: 'participants',
			rejection_comment_visibility: 'requester',
		})
	})

	it('round-trips every key through compose and decompose', () => {
		const stored = {
			rejection_enabled: true,
			rejection_behavior: 'cancel',
			rejection_comment_mode: 'optional',
			rejection_visibility: 'public',
			rejection_comment_visibility: 'participants',
		}

		expect(decomposeSignatureRejectionValue(composeSignatureRejectionValue(stored))).toEqual(stored)
	})

	it('keeps the prepared values while rejection is disabled', () => {
		const prepared = { ...configured, enabled: false }

		expect(normalizeSignatureRejectionDraftValue(prepared)).toEqual(prepared)
		expect(decomposeSignatureRejectionValue(prepared)).toEqual({
			rejection_enabled: false,
			rejection_behavior: 'continue',
			rejection_comment_mode: 'required',
			rejection_visibility: 'participants',
			rejection_comment_visibility: 'requester',
		})
	})

	it('keeps the comment audience while comments are disabled', () => {
		const value = { ...configured, commentMode: 'disabled', commentVisibility: 'public' }

		expect(normalizeSignatureRejectionDraftValue(value).commentVisibility).toBe('public')
		expect(decomposeSignatureRejectionValue(value).rejection_comment_visibility).toBe('public')
	})

	it('summarizes a disabled policy in one word', () => {
		expect(summarizeSignatureRejectionDraftValue({ ...configured, enabled: false }, tFn)).toBe('Disabled')
	})

	it('summarizes an enabled policy with its outcome and comment mode', () => {
		expect(summarizeSignatureRejectionDraftValue(configured, tFn)).toBe('Let the others keep signing · Required comment')
		expect(summarizeSignatureRejectionDraftValue({ ...configured, behavior: 'cancel', commentMode: 'disabled' }, tFn))
			.toBe('Cancel the signing request · No comment')
	})

	it('does not escape translated labels twice in the summary', () => {
		const spy = vi.fn(tFn)

		summarizeSignatureRejectionDraftValue(configured, spy)

		expect(spy).toHaveBeenLastCalledWith('libresign', '{behavior} · {comment}', expect.any(Object), { escape: false })
	})
})
