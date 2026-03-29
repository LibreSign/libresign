/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import { resolve } from 'node:path'

const patchPdfElementsRuntimeFixes = {
	name: 'patch-pdf-elements-runtime-fixes',
	enforce: 'pre',
	transform(code, id) {
		if (!id.includes('/@libresign/pdf-elements/')) {
			return null
		}
		if (!id.endsWith('/dist/index.mjs')
			&& !id.endsWith('/src/components/DraggableElement.vue')
			&& !id.endsWith('/src/components/PDFElements.vue')) {
			return null
		}

		let replaced = code

		// Drag/resize listeners must be non-passive because handleMove calls preventDefault.
		replaced = replaced.replace(
			/window\.addEventListener\((['"])touchmove\1,\s*this\.boundHandleMove\)/g,
			'window.addEventListener($1touchmove$1, this.boundHandleMove, { passive: false })',
		)

		// Adding-mode touchmove also needs to be non-passive.
		replaced = replaced.replace(
			/document\.addEventListener\((['"])touchmove\1,\s*this\.handleMouseMove,\s*\{\s*passive:\s*(?:!0|true)\s*\}\)/g,
			'document.addEventListener($1touchmove$1, this.handleMouseMove, { passive: false })',
		)

		// Guard against race where add mode ends while RAF callback is still queued.
		replaced = replaced.replace(
			/const s = this\.pendingHoverClientPos;\s*if \(!s\) return;/g,
			'if (!this.isAddingMode || !this.previewElement) { this.pendingHoverClientPos = null; return; } const s = this.pendingHoverClientPos; if (!s) return;',
		)

		// Defensive access to preview element dimensions in async mobile flow.
		replaced = replaced.replace(/this\.previewElement\.width/g, '(this.previewElement?.width || 0)')
		replaced = replaced.replace(/this\.previewElement\.height/g, '(this.previewElement?.height || 0)')

		// Keep toolbar above by default, but place below when signature is near top.
		replaced = replaced.replace(
			/const e = this\.pagesScale \|\| 1, t = this\.mode === "drag", i = this\.mode === "resize", s = t \? this\.offsetX : 0, n = t \? this\.offsetY : 0, a = i \? this\.resizeOffsetX : 0, o = i \? this\.resizeOffsetY : 0, r = i \? this\.resizeOffsetW : 0, h = this\.object\.x \+ s \+ a, l = this\.object\.y \+ n \+ o, u = this\.object\.width \+ r, d = l - 60, g = d < 0 \? l \+ 8 : d;/g,
			'const e = this.pagesScale || 1, t = this.mode === "drag", i = this.mode === "resize", s = t ? this.offsetX : 0, n = t ? this.offsetY : 0, a = i ? this.resizeOffsetX : 0, o = i ? this.resizeOffsetY : 0, r = i ? this.resizeOffsetW : 0, h = i ? this.resizeOffsetH : 0, l = this.object.x + s + a, u = this.object.y + n + o, c = this.object.width + r, d = this.object.height + h, g = u * e < 72, f = g ? u + d : u, b = g ? "translate(-50%, 8px)" : "translate(-50%, calc(-100% - 8px))";',
		)
		replaced = replaced.replace(
			/left: `\$\{\(h \+ u \/ 2\) \* e\}px`,\s*top: `\$\{g \* e\}px`,\s*transform: "translateX\(-50%\)"/g,
			'left: `${(l + c / 2) * e}px`, top: `${f * e}px`, transform: b',
		)

		return replaced === code ? null : { code: replaced, map: null }
	},
}

export default createAppConfig({
	main: resolve('src/main.ts'),
	init: resolve('src/init.ts'),
	tab: resolve('src/tab.ts'),
	settings: resolve('src/settings.ts'),
	external: resolve('src/external.ts'),
	validation: resolve('src/validation.ts'),
}, {
	config: {
		server: {
			port: 3000,
			host: '0.0.0.0',
		},
		resolve: {
			alias: {
				'@': resolve(import.meta.dirname, 'src'),
			},
		},
		plugins: [
			patchPdfElementsRuntimeFixes,
			{
				name: 'vue-devtools',
				config(_, { mode }) {
					return {
						define: {
							__VUE_PROD_DEVTOOLS__: mode !== 'production',
						},
					}
				},
			},
		],
	},
})
