/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * File status constants
 * Synchronized with backend FileStatus enum (lib/Enum/FileStatus.php)
 */
export const FILE_STATUS = Object.freeze({
	NOT_LIBRESIGN_FILE: -1,
	DRAFT: 0,
	ABLE_TO_SIGN: 1,
	PARTIAL_SIGNED: 2,
	SIGNED: 3,
	DELETED: 4,
	SIGNING_IN_PROGRESS: 5,
})

/**
 * Sign request status constants
 * Synchronized with backend SignRequestStatus enum (lib/Enum/SignRequestStatus.php)
 */
export const SIGN_REQUEST_STATUS = Object.freeze({
	PENDING: 0,
	SIGNED: 1,
	REJECTED: 2,
	DELETED: 3,
})

/**
 * Signature flow types
 */
export const SIGNATURE_FLOW = Object.freeze({
	NONE: 0,
	SEQUENTIAL: 1,
	PARALLEL: 2,
})

/**
 * Identify method constants
 */
export const IDENTIFY_METHOD = Object.freeze({
	ACCOUNT: 'account',
	EMAIL: 'email',
	PHONE: 'phone',
	CLICK_TO_SIGN: 'clickToSign',
	PASSWORD: 'password',
	TELEGRAM: 'telegram',
	SIGNAL: 'signal',
})

/**
 * Sign method constants
 */
export const SIGN_METHOD = Object.freeze({
	PASSWORD: 'password',
	EMAIL_TOKEN: 'emailToken',
	CLICK_TO_SIGN: 'clickToSign',
	SMS_TOKEN: 'smsToken',
	TELEGRAM_TOKEN: 'telegramToken',
	SIGNAL_TOKEN: 'signalToken',
	WHATSAPP_TOKEN: 'whatsappToken',
	XMPP_TOKEN: 'xmppToken',
})

/**
 * Envelope name validation constraints
 */
export const ENVELOPE_NAME_MIN_LENGTH = 1
export const ENVELOPE_NAME_MAX_LENGTH = 255
