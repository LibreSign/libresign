/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const SIGN_STATUS = Object.freeze({
	NOT_LIBRESIGN_FILE: -1,
	DRAFT: 0,
	ABLE_TO_SIGN: 1,
	PARTIAL_SIGNED: 2,
	SIGNED: 3,
	DELETED: 4,
})

export {
	SIGN_STATUS,
}
