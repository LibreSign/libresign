<template>
	<Content app-name="libresign" class="jumbotron">
		<div class="container">
			<div class="image">
				<img :src="image" draggable="false">
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
				<div v-if="hasInfo" class="infor-container">
					<div class="infor-bg">
						<div class="infor">
							<div class="header">
								<img class="icon" :src="infoIcon">
								<h1>{{ t('libresign', 'Document Informations') }}</h1>
							</div>
							<div class="info-document">
								<p>
									<b>{{ document.name }}</b>
								</p>
								<a class="button" :href="linkToDownload(document.file)"> {{ t('libresign', 'View') }} </a>
							</div>
						</div>
					</div>
					<div class="infor-bg signed">
						<div class="header">
							<img class="icon" :src="signatureIcon">
							<h1>{{ t('libresign', 'Subscriptions:') }}</h1>
						</div>

						<!-- <span>total:  {{ document.signatures.length }} </span> -->
						<div v-for="item in document.signatures "
							id="sign"
							:key="item.fululName"
							class="scroll">
							<div class="subscriber">
								<span><b>{{ item.displayName ? item.displayName : "None" }}</b></span>
								<span>{{ formatDate(item.signed) }}</span>
							</div>
						</div>
					</div>
					<button type="primary" class="btn- btn-return" @click.prevent="changeInfo">
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
import iconA from '../../img/info-circle-solid.svg'
import iconB from '../../img/file-signature-solid.svg'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
// eslint-disable-next-line
import {toDate, format} from 'date-fns'

export default {
	name: 'Validation',

	components: {
		Content,
	},

	data() {
		return {
			image: BackgroundImage,
			infoIcon: iconA,
			signatureIcon: iconB,
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
		linkToDownload(val) {
			return val
		},
		changeInfo() {
			this.hasInfo = !this.hasInfo
		},
		formatDate(date) {
			if (date != null) {
				return format(toDate(parseInt(date)), 'MM-dd-yyyy')
			} else {
				return t('libresign', 'no date')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../assets/styles/validation.scss';
</style>
