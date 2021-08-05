<template>
	<Content app-name="libresign" class="jumbotron">
		<div class="container">
			<div class="image">
				<img :src="image" draggable="false">
			</div>
			<div id="dataUUID">
				<form v-show="!hasInfo" @submit="(e) => e.preventDefault()">
					<h1>{{ t('libresign', 'Validate Subscription.') }}</h1>
					<h3>{{ t('libresign', 'Enter the ID or UUID of the document to validate.') }}</h3>
					<input v-model="myUuid" type="text">
					<button :class="hasLoading ? 'btn-load primary loading':'btn'" @click.prevent="validate(myUuid)">
						{{ t('libresign', 'Validation') }}
					</button>
				</form>

				<div v-if="hasInfo" class="infor-container">
					<div class="infor-bg">
						<div class="infor-header">
							<div class="header">
								<img class="icon" :src="infoIcon">
								<h1>{{ t('libresign', 'Document Informations') }}</h1>
							</div>
							<div class="line">
								<div class="line-group">
									<h3>{{ t('libresign', 'Document Name:') }}</h3>
									<span>{{ document.name }}</span>
								</div>
								<div class="line-group">
									<h3>{{ t('libresign', 'Created in:') }}</h3>
									<span>{{ 'data' }}</span>
								</div>
							</div>
							<div class="line">
								<div class="line-group">
									<h3>{{ t('libresign', 'Document hash:') }}</h3>
									<span>{{ myUuid }}</span>
								</div>
							</div>
							<div class="line">
								<div id="legal-information" class="line-group">
									<h3>{{ t('libresign', 'Legal Information:') }}</h3>
									<span class="legal-information">{{ legalInformation }}</span>
								</div>
							</div>
							<a class="button" :href="linkToDownload(document.file)"> {{ t('libresign', 'View') }} </a>
						</div>

						<div class="infor-bg signed">
							<div class="header">
								<img class="icon" :src="signatureIcon">
								<h1>{{ t('libresign', 'Signatures:') }}</h1>
							</div>
							<div class="infor-content">
								<div v-for="item in document.signers"
									id="sign"
									:key="item.fullName"
									class="scroll">
									<div class="subscriber">
										<span><b>{{ getName(item) }}</b></span>
										<span v-if="item.signed" class="data-signed">{{ formatData(item.signed) }} </span>
										<span v-else>{{ t('libresign', 'No date') }}</span>
									</div>
								</div>
							</div>
						</div>
						<button type="primary" class="btn- btn-return" @click.prevent="changeInfo">
							{{ t('libresign', 'Return') }}
						</button>
					</div>
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
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { fromUnixTime } from 'date-fns'

export default {
	name: 'Validation',

	components: {
		Content,
	},

	props: {
		uuid: {
			type: String,
			required: false,
			default: '',
		},
	},

	data() {
		return {
			image: BackgroundImage,
			infoIcon: iconA,
			signatureIcon: iconB,
			myUuid: this.uuid ? this.uuid : '',
			hasInfo: false,
			hasLoading: false,
			document: {},
			documentUuid: '',
			legalInformation: '',
		}
	},
	watch: {
		'$route.params'(toParams, previousParams) {
			this.validate(toParams.uuid)
			this.myUuid = toParams.uuid
		},
	},
	created() {
		this.getData()
		if (this.myUuid.length > 0) {
			this.validate(this.myUuid)
		}
	},
	methods: {
		validate(id) {
			if (id.length >= 8) {
				this.validateByUUID(id)
			} else {
				this.validateByNodeID(id)
			}
		},
		async validateByUUID(uuid) {
			this.hasLoading = true

			try {
				const response = await axios.get(generateUrl(`/apps/libresign/api/0.1/file/validate/uuid/${uuid}`))
				showSuccess(t('libresign', 'This document is valid'))
				this.document = response.data
				this.hasInfo = true
				this.hasLoading = false
			} catch (err) {
				this.hasLoading = false
				showError(err.response.data.errors[0])
			}
		},
		async validateByNodeID(nodeId) {
			this.hasLoading = true
			try {
				const response = await axios.get(generateUrl(`/apps/libresign/api/0.1/file/validate/file_id/${nodeId}`))
				showSuccess(t('libresign', 'This document is valid'))
				this.document = response.data
				this.hasInfo = true
				this.hasLoading = false
			} catch (err) {
				this.hasLoading = false
				showError(err.response.data.errors[0])
			}
		},
		async getData() {
			const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1', 2) + 'config/apps/libresign/legal_information', {})
			this.legalInformation = response.data.ocs.data.data
		},
		getName(user) {
			if (user.fullName) {
				return user.fullName
			} else if (user.displayName) {
				return user.displayName
			} else if (user.email) {
				return user.email
			}

			return 'None'
		},
		linkToDownload(val) {
			return val
		},
		changeInfo() {
			this.hasInfo = !this.hasInfo
			this.uuid = ''
		},
		formatData(data) {
			try {
				return fromUnixTime(data).toLocaleDateString()
			} catch {
				return t('libresign', 'No date')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../assets/styles/validation.scss';
@import '../assets/styles/loading.scss';
</style>
