<template>
	<NcContent app-name="libresign" class="jumbotron with-sidebar--full">
		<div class="container">
			<div class="image">
				<img :src="image" draggable="false">
			</div>
			<div id="dataUUID">
				<form v-show="!hasInfo" @submit="(e) => e.preventDefault()">
					<h1>{{ title }}</h1>
					<h3>{{ legend }}</h3>
					<input v-model="myUuid" type="text">
					<NcButton type="primary"
						@click.prevent="validate(myUuid)">
						<template #icon>
							<NcLoadingIcon v-if="hasLoading" :size="20" />
						</template>
						{{ buttonTitle }}
					</NcButton>
				</form>
				<div v-if="hasInfo" class="infor-container">
					<div class="infor-bg">
						<div class="infor">
							<div class="header">
								<img class="icon" :src="infoIcon">
								<h1>{{ infoDocument }}</h1>
							</div>
							<div class="info-document">
								<p>
									<b>{{ document.name }}</b>
								</p>

								<span class="legal-information">
									{{ legalInformation }}
								</span>

								<NcButton type="primary"
									@click="viewDocument(document.file)">
									<template #icon>
										<NcLoadingIcon v-if="hasLoading" :size="20" />
									</template>
									{{ t('libresign', 'View') }}
								</NcButton>
							</div>
						</div>
					</div>
					<div class="infor-bg signed">
						<div class="header">
							<img class="icon" :src="signatureIcon">
							<h1>{{ t('libresign', 'Signatories:') }}</h1>
						</div>
						<div class="infor-content">
							<div v-for="item in document.signers"
								id="sign"
								:key="item.fullName"
								class="scroll">
								<div class="subscriber">
									<span><b>{{ getName(item) }}</b></span>
									<span v-if="item.signed" class="data-signed">
										<Moment :timestamp="item.signed" />
									</span>
									<span v-else>{{ noDateMessage }}</span>
								</div>
							</div>
						</div>
					</div>
					<NcButton type="primary"
						@click.prevent="goBack">
						{{ t('libresign', 'Return') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcContent>
</template>

<script>
import axios from '@nextcloud/axios'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import { loadState } from '@nextcloud/initial-state'
import BackgroundImage from '../../img/logo-gray.svg'
import iconA from '../../img/info-circle-solid.svg'
import iconB from '../../img/file-signature-solid.svg'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import Moment from './../Components/Moment.vue'
import logger from '../logger.js'

export default {
	// eslint-disable-next-line vue/match-component-file-name
	name: 'Validation',

	components: {
		Moment,
		NcContent,
		NcButton,
		NcLoadingIcon,
	},

	data() {
		const fileInfo = loadState('libresign', 'file_info', {})
		return {
			image: BackgroundImage,
			infoDocument: t('libresign', 'Document Informations'),
			infoIcon: iconA,
			signatureIcon: iconB,
			title: t('libresign', 'Validate Subscription.'),
			legend: t('libresign', 'Enter the ID or UUID of the document to validate.'),
			buttonTitle: t('libresign', 'Validation'),
			noDateMessage: t('libresign', 'No date'),
			myUuid: this.$route.params?.uuid ?? '',
			hasInfo: Object.keys(fileInfo).length > 0,
			hasLoading: false,
			document: fileInfo,
			documentUuid: '',
			legalInformation: '',
		}
	},
	watch: {
		'$route.params.uuid'(uuid) {
			this.validate(uuid)
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
			if (id === this.document?.uuid) {
				showSuccess(t('libresign', 'This document is valid'))
				this.hasInfo = true
				return
			}
			if (id.length >= 8) {
				this.validateByUUID(id)
			} else {
				this.validateByNodeID(id)
			}
		},
		async validateByUUID(uuid) {
			this.hasLoading = true
			try {
				const response = await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/uuid/${uuid}`))
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
				const response = await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${nodeId}`))
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
			this.legalInformation = loadState('libresign', 'legal_information', '')
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
		viewDocument(val) {
			window.open(`${val}?_t=${Date.now()}`)
		},
		goBack() {
			// Redirect if have path to go back
			const urlParams = new URLSearchParams(window.location.search)
			if (urlParams.has('path')) {
				try {
					const redirectPath = window.atob(urlParams.get('path')).toString()
					if (redirectPath.startsWith('/apps')) {
						window.location = generateUrl(redirectPath)
						return
					}
				} catch (error) {
					logger.error('Failed going back', { error })
				}
			}
			this.hasInfo = !this.hasInfo
			this.myUuid = this.$route.params.uuid
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../assets/styles/validation';
</style>
