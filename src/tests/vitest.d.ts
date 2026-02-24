/*
 * SPDX-FileCopyrightText: 2024 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import 'vitest'

declare module 'vitest' {
	export interface Mock<T = any, Y extends any[] = any> {
		(...args: Y): T
		mockReturnValue(value: T): this
		mockResolvedValue(value: Awaited<T>): this
		mockRejectedValue(value: any): this
		mockImplementation(fn: (...args: Y) => T): this
		mockClear(): void
		mockReset(): void
		mockRestore(): void
		mock: {
			calls: Y[]
			results: Array<{ type: 'return' | 'throw', value: T }>
		}
	}
}
