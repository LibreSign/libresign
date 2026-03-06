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

<script setup>
import { mdiCloseCircle } from '@mdi/js'
import { computed, onMounted, ref } from 'vue'

import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

defineOptions({
	name: 'Signature',
})

const props = defineProps({
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
})

const emit = defineEmits(['onUpdate', 'onDelete'])

const startX = ref(null)
const startY = ref(null)
const operation = ref('')
const directions = ref([])
const dx = ref(0)
const dy = ref(0)
const dw = ref(0)
const dh = ref(0)

const isInteractive = computed(() => !props.readOnly && !props.disableInteractions)

const ratio = computed(() => {
	const baseWidth = props.originWidth || props.width
	const baseHeight = props.originHeight || props.height
	if (!baseWidth || !baseHeight) {
		return 1
	}
	return baseWidth / baseHeight
})

const containerStyle = computed(() => {
	if (props.useContainerSize) {
		return {
			width: '100%',
			height: '100%',
			transform: translateCoordinates(),
		}
	}

	return {
		width: `${props.width + dw.value}px`,
		height: `${Math.round((props.width + dw.value) / ratio.value)}px`,
		transform: translateCoordinates(),
	}
})

function translateCoordinates() {
	return `translate(${dx.value}px, ${dy.value}px)`
}

async function render() {
	let scale = 1
	const maxTarget = 500
	if (props.width > maxTarget) {
		scale = maxTarget / props.width
	}
	if (props.height > maxTarget) {
		scale = Math.min(scale, maxTarget / props.height)
	}
	emit('onUpdate', {
		width: props.width * scale,
		height: props.height * scale,
	})
}

function handleMousedown(event) {
	return { detail: { x: event.clientX, y: event.clientY, target: event.target } }
}

function handleMousemove(event) {
	return { detail: { x: event.clientX, y: event.clientY } }
}

function handleMouseup(event) {
	return { detail: { x: event.clientX, y: event.clientY } }
}

function handleTouchStart(event) {
	const touch = event.touches[0]
	return { detail: { x: touch.clientX, y: touch.clientY, target: event.currentTarget } }
}

function handleTouchmove(event) {
	const touch = event.touches[0]
	return { detail: { x: touch.clientX, y: touch.clientY } }
}

function handleTouchend(event) {
	const touch = event.changedTouches[0]
	return { detail: { x: touch.clientX, y: touch.clientY } }
}

function handlePanMove(event) {
	let coordinate
	if (event.type === 'mousemove') {
		coordinate = handleMousemove(event)
	}
	if (event.type === 'touchmove') {
		coordinate = handleTouchmove(event)
	}
	if (!coordinate) {
		return
	}

	const nextDx = (coordinate.detail.x - startX.value) / props.pageScale
	const nextDy = (coordinate.detail.y - startY.value) / props.pageScale
	if (operation.value === 'move') {
		dx.value = nextDx
		dy.value = nextDy
	} else if (operation.value === 'scale') {
		if (directions.value.includes('left')) {
			dx.value = nextDx
			dw.value = -nextDx
		}
		if (directions.value.includes('top')) {
			dy.value = nextDy
			dh.value = -nextDy
		}
		if (directions.value.includes('right')) {
			dw.value = nextDx
		}
		if (directions.value.includes('bottom')) {
			dh.value = nextDy
		}
	}
}

function resetInteractionState() {
	dx.value = 0
	dy.value = 0
	dw.value = 0
	dh.value = 0
	directions.value = []
	operation.value = ''
}

function handlePanEnd(event) {
	if (event.type === 'mouseup') {
		handleMouseup(event)
	}
	if (event.type === 'touchend') {
		handleTouchend(event)
	}

	if (operation.value === 'move') {
		emit('onUpdate', {
			x: props.x + dx.value,
			y: props.y + dy.value,
		})
		resetInteractionState()
		return
	}

	if (operation.value === 'scale') {
		emit('onUpdate', {
			x: props.x + dx.value,
			y: props.y + dy.value,
			width: props.width + dw.value,
			height: Math.round((props.width + dw.value) / ratio.value),
		})
		resetInteractionState()
	}
}

function handlePanStart(event) {
	if (props.readOnly || props.disableInteractions) {
		return
	}

	let coordinate
	if (event.type === 'mousedown') {
		coordinate = handleMousedown(event)
	}
	if (event.type === 'touchstart') {
		coordinate = handleTouchStart(event)
	}
	if (!coordinate) {
		return
	}

	startX.value = coordinate.detail.x
	startY.value = coordinate.detail.y
	if (coordinate.detail.target === event.currentTarget) {
		operation.value = 'move'
		return
	}

	operation.value = 'scale'
	directions.value = coordinate.detail.target.dataset.direction.split('-')
}

function onDelete() {
	emit('onDelete')
}

onMounted(() => {
	void render()
})

defineExpose({
	startX,
	startY,
	operation,
	directions,
	dx,
	dy,
	dw,
	dh,
	containerStyle,
	isInteractive,
	ratio,
	translateCoordinates,
	render,
	handleMousedown,
	handleMousemove,
	handleMouseup,
	handleTouchStart,
	handleTouchmove,
	handleTouchend,
	handlePanMove,
	handlePanEnd,
	handlePanStart,
	onDelete,
})
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
