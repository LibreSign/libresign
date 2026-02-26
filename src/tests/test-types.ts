/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { VueWrapper } from '@vue/test-utils'
import type { Mock } from 'vitest'

/**
 * Generic wrapper type for Vue components in tests
 */
export type ComponentWrapper<T = unknown> = VueWrapper<T>

/**
 * Mock function with common test methods
 */
export type MockFunction<T extends (...args: unknown[]) => unknown = (...args: unknown[]) => unknown> = Mock<ReturnType<T>, Parameters<T>>

/**
 * Partial store type for Pinia stores in tests
 */
export type PartialStore<T> = Partial<T> & Record<string, unknown>

/**
 * Translation function type (Nextcloud i18n)
 */
export type TranslationFunction = (app: string, text: string, vars?: Record<string, string | number>) => string

/**
 * Plural translation function type
 */
export type PluralTranslationFunction = (app: string, singular: string, plural: string, count: number, vars?: Record<string, string | number>) => string

/**
 * Mock return value type helper
 */
export type MockReturnValue<T> = T extends (...args: unknown[]) => infer R ? R : never
