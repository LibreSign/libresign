/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare module 'blueimp-md5' {
	const md5: (value: string) => string
	export default md5
}

declare module 'crypto-js/md5' {
	const md5: (value: string) => { toString(): string }
	export default md5
}