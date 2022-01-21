<script>
export default {
	name: 'Documents',
}
</script>

<template>
	<div class="documents">
		<h1>{{ t('libresign', 'Your profile documents') }}</h1>
	</div>
</template>

<style lang="scss" scoped>
.documents {
	align-items: flex-start;
	width: 100%;

	h1{
		font-size: 1.3rem;
		font-weight: bold;
		border-bottom: 1px solid #000;
		padding-left: 5px;
		width: 100%;
		display: block;
	}
}
</style>
