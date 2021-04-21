<template>
	<Content app-name="libresign">
		<div class="container">
			<div class="image">
				<img :src="image">
			</div>
			<div id="dataUUID">
				<form>
					<h1>{{ title }}</h1>
					<h3>{{ legend }}</h3>
					<input v-model="uuid" type="text">
					<button class="btn" @click.prevent="validateByUUID">
						{{ buttonTitle }}
					</button>
				</form>
			</div>
		</div>
	</Content>
</template>

<script>
import axios from '@nextcloud/axios'
import Content from '@nextcloud/vue/dist/Components/Content'
import BackgroundImage from '../assets/images/bg.png'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'Validation',

	components: {
		Content,
	},

	data() {
		return {
			image: BackgroundImage,
			title: t('libresign', 'Validate Subscription.'),
			legend: t('libresign', 'Enter the UUID of the document to validate.'),
			buttonTitle: t('libresign', 'Validation'),
			uuid: '',
		}
	},
	methods: {
		async validateByUUID() {
			// eslint-disable-next-line no-console
			console.log(this.uuid)
			try {
				console.info('resp')
				const response = await axios.get(generateUrl(`/apps/libresign/api/0.1/file/validate/${this.uuid}`))
				console.info(response)
			} catch (err) {
				console.error(err)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../assets/styles/validation.scss';
</style>
