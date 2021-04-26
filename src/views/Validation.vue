<template>
	<Content app-name="libresign">
		<div class="container">
			<div class="image">
				<img :src="image">
			</div>
			<div id="dataUUID">
				<form v-show="!hasInfo" @submit="(e) => e.preventDefault()">
					<h1>{{ title }}</h1>
					<h3>{{ legend }}</h3>
					<input v-model="uuid" type="text">
					<button class="btn" @click.prevent="validateByUUID">
						{{ buttonTitle }}
					</button>
				</form>
				<div v-if="hasInfo" class="infor">
					<h1>{{ t('libresign', 'Document Informations') }}</h1>
					<div class="info-document">
						<p>
							<strong>
								Nome:
							</strong>
							{{ document.name }}
						</p>
						<p>Descrição: </p>
						<p>Arquivo: {{ document.file }}</p>
						<p>
							<strong>
								Assinaturas:
							</strong>
						</p>
						<span>total:  {{ document.signatures.length }} </span>
						<div v-for="item in document.signatures " id="sign" :key="item.fululName">
							<span>Nome: {{ item.displayName ? item.displayName : "None" }}</span>
							<span>Data de assinatura: {{ item.signed }}</span>
						</div>
					</div>
					<button type="primary" class="btn-" @click.prevent="changeInfo">
						{{ t('libresign', 'Return') }}
					</button>
				</div>
			</div>
		</div>
	</Content>
</template>

<script>
import axios from '@nextcloud/axios'
import Content from '@nextcloud/vue/dist/Components/Content'
import BackgroundImage from '../assets/images/bg.png'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

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
			hasInfo: false,
			document: {},
		}
	},
	methods: {
		async validateByUUID() {
			// eslint-disable-next-line no-console
			console.log(this.uuid)
			try {
				const response = await axios.get(generateUrl(`/apps/libresign/api/0.1/file/validate/${this.uuid}`))
				console.info(response)
				showSuccess('ok')
				this.document = response.data
				this.hasInfo = true
			} catch (err) {
				showError(err.response.data.errors[0])
			}
		},
		changeInfo() {
			this.hasInfo = !this.hasInfo
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../assets/styles/validation.scss';
</style>
