<template>
	<div v-show="hasVisible" class="container">
		<span :class="icon" />
		<span>{{ message }}</span>
	</div>
</template>

<script>
export default {
	name: 'Tooltip',
	props: {
		icon: {
			type: String,
			required: false,
			default: 'icon-details',
			validator: (value) => {
				return ['icon-error', 'icon-details'].includes(value)
			},
		},
		type: {
			type: String,
			required: false,
			default: 'info',
			validator: (value) => {
				return ['info', 'error'].includes(value)
			},
		},
		time: {
			type: Number,
			default: 10,
			required: false,
		},
		message: {
			type: String,
			required: true,
			default: 'Error',
		},
	},
	data() {
		return {
			hasVisible: true,
		}
	},
	created() {
		this.timerVisible()
	},
	methods: {
		enabled() {
			this.hasVisible = true
			this.timerVisible()
		},
		timerVisible() {
			setTimeout(() => {
				this.hasVisible = false
			}, this.time * 1000)
		},
	},
}
</script>

<style lang="scss" scoped>
	.container{
		width: 100%;
		padding: 16px;
		border: 1px solid #cecece;
		border-radius: 15px;
		margin-bottom: 10px;
		background: #cecece;
	}
</style>
