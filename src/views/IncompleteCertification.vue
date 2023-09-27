<template>
	<div class="container">
		<div class="container-image">
			<img :src="image" draggable="false">
		</div>
		<h1 class="title">
			{{ t('libresign', 'Welcome to Libresign') }}
		</h1>
		<p>{{ t('libresign', 'The admin hasn\'t set up LibreSign yet, please wait.') }}</p>
	</div>
</template>

<script>

import BackgroundImage from '../assets/images/bg.png'

export default {
	name: 'IncompleteCertification',
	data() {
		return {
			image: BackgroundImage,
		}
	},
}
</script>

<style lang="scss" scoped>
.container{
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
	width: 100%;
	height: 100%;
	h1 {
		font-weight: bold;
		font-size: 24px;
		color: var(--color-main-text);
	}
}

.container-image {
	display: flex;
	justify-content: center;
	margin: 20px;

	img {
		width: 30%;
	}
}

</style>
