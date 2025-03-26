/**
 * SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export default {
	props: {
		source: {
			type: Object,
			required: true,
		},
		loading: {
			type: Boolean,
			required: true,
		},
	},
	computed: {
		mtime() {
			return new Date(this?.source?.created_at)
		},

		openedMenu: {
			get() {
				return this.actionsMenuStore.opened === this.source.nodeId
			},
			set(opened) {
				this.actionsMenuStore.opened = opened ? this.source.nodeId : null
			},
		},

		mtimeOpacity() {
			const maxOpacityTime = 31 * 24 * 60 * 60 * 1000 // 31 days

			const mtime = this.mtime?.getTime?.()
			if (!mtime) {
				return {}
			}

			// 1 = today, 0 = 31 days ago
			const ratio = Math.round(Math.min(100, 100 * (maxOpacityTime - (Date.now() - mtime)) / maxOpacityTime))
			if (ratio < 0) {
				return {}
			}
			return {
				color: `color-mix(in srgb, var(--color-main-text) ${ratio}%, var(--color-text-maxcontrast))`,
			}
		},
	},
	methods: {
		// Open the actions menu on right click
		onRightClick(event) {
			// If already opened, fallback to default browser
			if (this.openedMenu) {
				return
			}

			// Reset any right menu position potentially set
			const root = this.$el?.closest('main.app-content')
			root.style.removeProperty('--mouse-pos-x')
			root.style.removeProperty('--mouse-pos-y')

			this.actionsMenuStore.opened = this.source.nodeId

			// Prevent any browser defaults
			event.preventDefault()
			event.stopPropagation()
		},

		openDetailsIfAvailable(event) {
			event.preventDefault()
			event.stopPropagation()
			this.filesStore.selectFile(this.source.nodeId)
		},
	},
}
