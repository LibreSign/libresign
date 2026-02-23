<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="absolute left-0 top-0 select-none"
		:style="containerStyle">
		<div class="signature absolute w-full h-full"
			:class="[
				isInteractive ? 'cursor-grab' : '',
				operation === 'move' ? 'cursor-grabbing' : '',
				operation ? 'operation' : '',
			]"
			@mousedown="handlePanStart"
			@touchstart="handlePanStart">
			<div v-if="!fixSize && isInteractive"
				data-direction="left-top"
				class="absolute cursor-nwse-resize transform selector"
				:style="{ top: '0%', left: '0%' }" />
			<div v-if="!fixSize && isInteractive"
				data-direction="right-top"
				class="absolute cursor-nesw-resize transform selector"
				:style="{ top: '0%', left: '100%' }" />
			<div v-if="!fixSize && isInteractive"
				data-direction="left-bottom"
				class="absolute cursor-nesw-resize transform selector"
				:style="{ top: '100%', left: '0%' }" />
			<div v-if="!fixSize && isInteractive"
				data-direction="right-bottom"
				class="absolute cursor-nwse-resize transform selector"
				:style="{ top: '100%', left: '100%' }" />
		</div>
		<div v-if="isInteractive"
			class="absolute cursor-pointer transform delete"
			:style="{ top: '0%', left: '50%' }"
			@click="onDelete">
			<NcIconSvgWrapper class="w-full h-full icon-delete"
				:path="mdiCloseCircle"
				:size="25" />
		</div>
		<div class="w-full h-full border border-gray-400 border-dashed content">
			{{ displayName }}
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'

import { mdiCloseCircle } from '@mdi/js'

import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

export default {
	name: 'Signature',
	components: {
		NcIconSvgWrapper,
	},
	setup() {
		return { mdiCloseCircle }
	},
	props: {
		displayName: {
			type: String,
			default: '',
	},
		width: {
			type: Number,
			default: 0,
	},
		height: {
			type: Number,
			default: 0,
	},
		originWidth: {
			type: Number,
			default: 0,
	},
		originHeight: {
			type: Number,
			default: 0,
	},
		x: {
			type: Number,
			default: 0,
	},
		y: {
			type: Number,
			default: 0,
	},
		pageScale: {
			type: Number,
			default: 1,
	},
		fixSize: {
			type: Boolean,
			default: false,
	},
	readOnly: {
		type: Boolean,
		default: false,
	},
	useContainerSize: {
		type: Boolean,
		default: false,
	},
	disableInteractions: {
		type: Boolean,
		default: false,
	},
},
	data() {
		return {
			startX: null,
			startY: null,
			operation: '',
			directions: [],
			dx: 0,
			dy: 0,
			dw: 0,
			dh: 0,
		}
	},
	computed: {
		containerStyle() {
			if (this.useContainerSize) {
				return {
					width: '100%',
					height: '100%',
					transform: this.translateCoordinates(),
				}
			}
			return {
				width: `${this.width + this.dw}px`,
				height: `${Math.round((this.width + this.dw) / this.ratio)}px`,
				transform: this.translateCoordinates(),
			}
		},
		isInteractive() {
			return !this.readOnly && !this.disableInteractions
		},
		ratio() {
			const baseWidth = this.originWidth || this.width
			const baseHeight = this.originHeight || this.height
			if (!baseWidth || !baseHeight) {
				return 1
			}
			return baseWidth / baseHeight
		},
	},
	async mounted() {
		await this.render()
	},
	methods: {
		t,
		translateCoordinates() {
			return `translate(${this.dx}px, ${this.dy}px)`
		},
		async render() {
			let scale = 1
			const MAX_TARGET = 500
			if (this.width > MAX_TARGET) {
				scale = MAX_TARGET / this.width
			}
			if (this.height > MAX_TARGET) {
				scale = Math.min(scale, MAX_TARGET / this.height)
			}
			// eslint-disable-next-line vue/custom-event-name-casing
			this.$emit('onUpdate', {
				width: this.width * scale,
				height: this.height * scale,
			})
		},
		handleMousedown(event) {
			return { detail: { x: event.clientX, y: event.clientY, target: event.target } }
		},
		handleMousemove(event) {
			return { detail: { x: event.clientX, y: event.clientY } }
		},
		handleMouseup(event) {
			return { detail: { x: event.clientX, y: event.clientY } }
		},
		handleTouchStart(event) {
			const touch = event.touches[0]
			return { detail: { x: touch.clientX, y: touch.clientY, target: event.currentTarget } }
		},
		handleTouchmove(event) {
			const touch = event.touches[0]
			return { detail: { x: touch.clientX, y: touch.clientY } }
		},
		handleTouchend(event) {
			const touch = event.changedTouches[0]
			return { detail: { x: touch.clientX, y: touch.clientY } }
		},
		handlePanMove(event) {
			let coordinate
			if (event.type === 'mousemove') {
				coordinate = this.handleMousemove(event)
			}
			if (event.type === 'touchmove') {
				coordinate = this.handleTouchmove(event)
			}

			const _dx = (coordinate.detail.x - this.startX) / this.pageScale
			const _dy = (coordinate.detail.y - this.startY) / this.pageScale
			if (this.operation === 'move') {
				this.dx = _dx
				this.dy = _dy
			} else if (this.operation === 'scale') {
				if (this.directions.includes('left')) {
					this.dx = _dx
					this.dw = -_dx
				}
				if (this.directions.includes('top')) {
					this.dy = _dy
					this.dh = -_dy
				}
				if (this.directions.includes('right')) {
					this.dw = _dx
				}
				if (this.directions.includes('bottom')) {
					this.dh = _dy
				}
			}
		},

		handlePanEnd(event) {
			if (event.type === 'mouseup') {
				this.handleMouseup(event)
			}
			if (event.type === 'touchend') {
				this.handleTouchend(event)
			}
			if (this.operation === 'move') {
			// eslint-disable-next-line vue/custom-event-name-casing
				this.$emit('onUpdate', {
					x: this.x + this.dx,
					y: this.y + this.dy,
				})
				this.dx = 0
				this.dy = 0
			} else if (this.operation === 'scale') {
			// eslint-disable-next-line vue/custom-event-name-casing
				this.$emit('onUpdate', {
					x: this.x + this.dx,
					y: this.y + this.dy,
					width: this.width + this.dw,
					height: Math.round((this.width + this.dw) / this.ratio),
				})
				this.dx = 0
				this.dy = 0
				this.dw = 0
				this.dh = 0
				this.directions = []
			}
			this.operation = ''
		},
		handlePanStart(event) {
			if (this.readOnly || this.disableInteractions) {
				return
			}
			let coordinate
			if (event.type === 'mousedown') {
				coordinate = this.handleMousedown(event)
			}
			if (event.type === 'touchstart') {
				coordinate = this.handleTouchStart(event)
			}
			if (!coordinate) return

			this.startX = coordinate.detail.x
			this.startY = coordinate.detail.y
			if (coordinate.detail.target === event.currentTarget) {
				return (this.operation = 'move')
			}
			this.operation = 'scale'
			this.directions = coordinate.detail.target.dataset.direction.split('-')
		},
		onDelete() {
			// eslint-disable-next-line vue/custom-event-name-casing
			this.$emit('onDelete')
		},
	},
}
</script>

<style lang="scss" scoped>
.signature {
	background-color: rgba(0, 0, 0, 0.2);
}
.operation {
	background-color: rgba(0, 0, 0, 0.3);
}
.content {
	color: var(--color-text-maxcontrast);
}

.selector {
	border-radius: 10px;
	width: 12px;
	height: 12px;
	margin-left: -6px;
	margin-top: -6px;
	background-color: #32b5fe;
	border: 1px solid #32b5fe;
}
.delete {
	border-radius: 10px;
	width: 18px;
	height: 18px;
	margin-left: -9px;
	margin-top: -9px;
	background-color: #ffffff;
}
</style>
