<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="absolute left-0 top-0 select-none"
		:style="{
			width: `${width + dw}px`,
			height: `${Math.round((width + dw) / ratio)}px`,
			transform: translateCoordinates(),
		}">
		<div class="signature absolute w-full h-full"
			:class="[
				!readOnly ? 'cursor-grab' : '',
				operation === 'move' ? 'cursor-grabbing' : '',
				operation ? 'operation' : '',
			]"
			@mousedown="handlePanStart"
			@touchstart="handlePanStart">
			<div v-if="!fixSize"
				data-direction="left-top"
				class="absolute cursor-nwse-resize transform selector"
				:style="{ top: '0%', left: '0%' }" />
			<div v-if="!fixSize"
				data-direction="right-top"
				class="absolute cursor-nesw-resize transform selector"
				:style="{ top: '0%', left: '100%' }" />
			<div v-if="!fixSize"
				data-direction="left-bottom"
				class="absolute cursor-nesw-resize transform selector"
				:style="{ top: '100%', left: '0%' }" />
			<div v-if="!fixSize"
				data-direction="right-bottom"
				class="absolute cursor-nwse-resize transform selector"
				:style="{ top: '100%', left: '100%' }" />
		</div>
		<div v-if="!readOnly"
			class="absolute cursor-pointer transform delete"
			:style="{ top: '0%', left: '50%' }"
			@click="onDelete">
			<CloseCircleIcon class="w-full h-full"
				text="Remove"
				fill-color="red"
				:size="25" />
		</div>
		<div class="w-full h-full border border-gray-400 border-dashed content">
			{{ displayName }}
		</div>
	</div>
</template>

<script>
import CloseCircleIcon from 'vue-material-design-icons/CloseCircle.vue'

import itemEventsMixin from '@libresign/vue-pdf-editor/src/Components/ItemEventsMixin.vue'

export default {
	name: 'Signature',
	components: {
		CloseCircleIcon,
	},
	mixins: [itemEventsMixin],
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
		ratio() {
			return this.originWidth / this.originHeight
		},
	},
	async mounted() {
		await this.render()
	},
	methods: {
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
			if (this.readOnly) {
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
