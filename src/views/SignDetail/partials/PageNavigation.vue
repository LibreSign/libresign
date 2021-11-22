<script>
import CounterBubble from '@nextcloud/vue/dist/Components/CounterBubble'
import { size } from 'lodash-es'

export default {
	name: 'PageNavigation',
	components: { CounterBubble },
	props: {
		value: {
			type: Number,
			required: true,
		},
		pages: {
			type: Array,
			required: true,
		},
		width: {
			type: String,
			required: true,
		},
	},
	computed: {
		size() {
			return size(this.pages)
		},
		actual() {
			return this.value
		},
		allowNext() {
			return this.actual < this.size
		},
		allowPrevious() {
			return this.value > 0
		},
	},
	methods: {
		next() {
			this.setPage(this.value + 1)
		},
		previous() {
			this.setPage(this.value - 1)
		},
		setPage(val) {
			this.$emit('input', val)
		},
	},
}
</script>

<template>
	<nav :style="{ width }" class="page-navigation">
		<button :disabled="!allowPrevious" class="primary" @click="previous">
			{{ t('libresign', 'Previous') }}
		</button>
		<CounterBubble type="outlined">
			{{ actual }}/{{ size }}
		</CounterBubble>
		<button :disabled="!allowNext" class="primary" @click="next">
			{{ t('libresign', 'Next') }}
		</button>
	</nav>
</template>

<style scoped>
.page-navigation {
	display: flex;
	flex-direction: row;
	flex-wrap: nowrap;
	justify-content: space-between;
	align-items: center;
	align-content: space-around;
}
</style>
