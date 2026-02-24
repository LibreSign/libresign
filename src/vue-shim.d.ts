/*
 * SPDX-FileCopyrightText: 2024 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * This file ensures proper type resolution for Vue 3
 * by explicitly referencing the runtime-dom types
 */

/// <reference types="@vue/runtime-dom" />

// Ensure all Vue exports are available
declare module 'vue' {
	export * from '@vue/runtime-dom'
	export { compile, compileToFunction } from '@vue/compiler-dom'
}
