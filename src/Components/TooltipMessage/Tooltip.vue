<template>
	<div class="container">
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
			default: 'info',
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
		message: {
			type: String,
			required: true,
			default: 'Error',
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
