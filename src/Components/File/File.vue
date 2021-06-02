<template>
	<div class="content-file">
		<img :src="srcImg">
		<div class="enDot">
			<div :class="'dot ' + status" />
			<span>pendente</span>
		</div>
		<h1>{{ name }}</h1>
	</div>
</template>

<script>
import ApplicationImagePdf from '../../assets/images/application-pdf.png'
export default {
	name: 'File',
	props: {
		name: {
			type: String,
			default: '',
			required: true,
		},
		status: {
			type: String,
			required: true,
			default: 'done',
			validator: () => ['done', 'canceled', 'pending'],
		},
	},
	data() {
		return {
			srcImg: ApplicationImagePdf,
		}
	},
}
</script>

<style lang="scss" scoped>
.content-file{
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
	min-width: 62px;
	min-height: 180px;
	margin: 20px;

	img{
		width: 90px;
	}

	.enDot{
		display: flex;
		flex-direction: row;
		align-content: center;
		margin: 5px;
		align-items: center;
		justify-content: center;

		.dot{
			width: 10px;
			height: 10px;
			border-radius: 50%;
			margin-right: 10px;
		}

		.done{
			background: #008000;
		}

		.canceled{
			background: #ff0000;
		}

		.pending {
			background: #d85a0b
		}

		span{
			font-size: 14px;
			font-weight: normal;
			text-align: center;
			color: rgba(0,0,0,.7);
		}
	}

	h1{
		font-size: 23px;
		width: 100%;
		text-align: center;
	}
}
</style>
