/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const NON_RETRIABLE_SIGN_ERROR_CODE = 422

type SignMethodLike = {
	method?: string
	modalCode?: string
}

type SignErrorLike = {
	code?: number | string
}

type SubmissionErrorLike = {
	errors?: SignErrorLike[]
}

export function shouldCloseCurrentModalOnSignError(
	methodConfig: SignMethodLike,
	signError: SubmissionErrorLike,
): boolean {
	const modalCode = methodConfig.modalCode || methodConfig.method || 'token'
	if (modalCode !== 'password') {
		return false
	}

	const errors = Array.isArray(signError.errors) ? signError.errors : []
	return errors.some((error) => Number(error?.code) === NON_RETRIABLE_SIGN_ERROR_CODE)
}
