<template>
	<div class="container">
		<div id="img" />
		<div class="content">
			<h1>404</h1>
			<h2>{{ error.message ? t('libresign', error.message) : 'Oops! Page Not Be Found' }}</h2>
			<p>{{ paragth }}</p>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'DefaultPageError',

	props: {
		error: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			paragth: t('libresign', 'Sorry but the page you are looking for does not exist, have been removed, name changed or is temporarily unavailable.'),
		}
	},
}
</script>

<style lang="scss" scoped>
.container{
	display: flex;
	flex-direction: row;
	align-items: center;
	justify-content: center;
	height: 100%;

	#img{
		background-image: url('../assets/images/sad-face-in-rounded-square.svg');
		height: 140px;
		width: 140px;
		background-repeat: no-repeat;
		background-size: cover;
		line-height: 17.6px;

		&::before {
			content: '';
			background-color: rgb(0, 245, 248);
			border: 50%;
		}
	}

}

.content{
	box-sizing: border-box;
	font-family: 'Nunito', sans-serif;
	text-transform: uppercase;
	color: rgb(21, 23, 25);
	max-width: 560px;
	padding-left: 50px;

	h1{
		font-size: 65px;
		font-weight: 700;
		line-height: 71.5px;
		margin-bottom: 10px;
		margin-top: 0px;
	}

	h2{
		font-size: 21px;
		font-weight: 400;
		line-height: 23.1px;
		margin: 0px;
		margin-bottom: 10px;
	}
	p{
		color: rgb(153, 159, 165);
		font-weight: 400;
		line-height: 17.6px;
	}
}
</style>
