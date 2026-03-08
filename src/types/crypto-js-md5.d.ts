/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare module 'crypto-js/md5' {
	const md5: (value: string) => { toString(): string }
	export default md5
}
