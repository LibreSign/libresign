<template>
	<div class="container">
		<div class="image">
			<img :src="image" draggable="false">
		</div>
		<div id="dataUUID">
			<form v-show="!hasInfo" @submit="(e) => e.preventDefault()">
				<h1>{{ title }}</h1>
				<NcTextField :value.sync="myUuid" :label="legend" />
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
							<NcNoteCard v-if="isAfterSigned" type="success">
								{{ t('libresign', 'Congratulations you have digitally signed a document using LibreSign') }}
							</NcNoteCard>
							<p>
								<b>{{ document.name }}</b>
							</p>
							<NcRichText class="legal-information"
								:text="legalInformation"
								:use-markdown="true" />

							<NcButton type="primary"
								@click="viewDocument()">
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
							:key="item.displayName"
							class="scroll">
							<div class="subscriber">
								<span><b>{{ getName(item) }}</b></span>
								<span v-if="item.signed" class="data-signed">
									{{ item.signed }}
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
</template>

<script>
import axios from '@nextcloud/axios'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcRichText from '@nextcloud/vue/dist/Components/NcRichText.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import JSConfetti from 'js-confetti'
import { loadState } from '@nextcloud/initial-state'
import BackgroundImage from '../../img/logo-gray.svg'
import iconA from '../../img/info-circle-solid.svg'
import iconB from '../../img/file-signature-solid.svg'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import logger from '../logger.js'

export default {
	name: 'Validation',

	components: {
		NcTextField,
		NcRichText,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
	},

	data() {
		const fileInfo = loadState('libresign', 'file_info', {})
		return {
			image: BackgroundImage,
			infoDocument: t('libresign', 'Document informations'),
			infoIcon: iconA,
			signatureIcon: iconB,
			title: t('libresign', 'Validate signature'),
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
	computed: {
		isAfterSigned() {
			return this.$route.params.isAfterSigned ?? false
		},
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
				this.document = response.data.ocs.data
				this.hasInfo = true
				this.hasLoading = false
				if (this.isAfterSigned) {
					const jsConfetti = new JSConfetti()
					jsConfetti.addConfetti()
				}
			} catch (err) {
				this.hasLoading = false
				showError(err.response.data.ocs.data.errors[0])
			}
		},
		async validateByNodeID(nodeId) {
			this.hasLoading = true
			try {
				const response = await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${nodeId}`))
				showSuccess(t('libresign', 'This document is valid'))
				this.document = response.data.ocs.data
				this.hasInfo = true
				this.hasLoading = false
				if (this.isAfterSigned) {
					const jsConfetti = new JSConfetti()
					jsConfetti.addConfetti()
				}
			} catch (err) {
				this.hasLoading = false
				showError(err.response.data.ocs.data.errors[0])
			}
		},
		async getData() {
			this.legalInformation = loadState('libresign', 'legal_information', '')
		},
		getName(user) {
			if (user.displayName) {
				return user.displayName
			} else if (user.email) {
				return user.email
			}

			return 'None'
		},
		viewDocument() {
			if (OCA?.Viewer !== undefined) {
				OCA.Viewer.open({
					fileInfo: {
						source: this.document.file,
						basename: this.document.name,
						mime: 'application/pdf',
					},
				})
			} else {
				window.open(`${this.document.file}?_t=${Date.now()}`)
			}
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
$title-font: 1.5rem;
$title-font-mobile: 1.3rem;
$date-signed-font: .7rem;

.jumbotron{
	padding: 0;
	margin: unset;
	width: unset;
	height: 100%;
}

.container{
	display: flex;
	align-items: center;
	justify-content: center;
	overflow-y: auto;
	width: 100%;
	height: 100%;

	.image{
		width: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
		img{
			width: 50%;
			max-width: 422px;
		}
		@media screen and (max-width: 900px) {
			display: none;
			width: 0%;
		}

	}
	#dataUUID{
		width: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
		@media screen and (max-width: 900px){
			width: 100%;
		}
	}
	.legal-information{
		opacity: 0.8;
		align-self: center;
		font-size: 1rem;
		overflow: scroll;
	}
}

form{
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 20px;
	margin: 20px;
	border-radius: 8px;
	max-width: 500px;
	width: 100%;
	box-shadow: 0 0 6px 0 var(--color-box-shadow);

	@media screen and (max-width: 900px) {
		width: 100%;
		display: flex;
		justify-content: center;
		align-items: center;
		max-width: 100%;
	}
}

h1{
	font-size: 24px;
	font-weight: bold;
	color: var(--color-main-text);
}

button{
	float: right;
	margin-top: 20px;
}

.infor{
	display: flex;
	flex-direction: column;
	h1{
		font-size: $title-font;
	}
}

.infor-container{
	width: 100%;
	margin-right: 20px;
}

.infor-bg{
	background-color: var(--color-main-background);
	padding: 20px 60px 20px 20px;
	border-radius: 8px;
	box-shadow: 0 0 6px 0 var(--color-box-shadow);

	.infor-content{
		display: flex;
		flex-direction: row;
		flex-wrap: wrap;
		overflow: scroll;
		height: 80%;
		width: 98%;
	}
}

.info-document{
	color: var(--color-main-text);
	display: flex;
	flex-direction: column;
	width: 100%;
	margin-left: 30px;
	justify-content: center;
	overflow: scroll;

	p{
		font-size: 1rem;
	}
	button{
		align-self: flex-end;
	}
	#sign {
		display: flex;
	}
}

.signed {
	margin-top: 10px;
	padding-right: 2px;
	strong {
		font-size: 22px;
		margin-bottom: 10px;
	}
	button {
		float: right;
	}
}

.scroll {
	max-height: 200px;
	display: flex;
	flex-wrap: wrap;
	min-width: 200px;
	max-width: 200px;
}

.subscriber {
	display: flex;
	flex-direction: column;
	color: var(--color-main-text);
	background-color: rgba(var(--color-info-rgb), 0.1);
	border-radius: 8px;
	padding: 5px 0px 5px 5px;
	margin: 5px 5px 0px 0px;
	min-height: 50px;
	max-height: 60px;
	padding-left: 10px;
	width: 100%;
	max-width: 98%;

	.data-signed {
		font-size: $date-signed-font;
	}
	b{
		display: block;
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

.header {
	display: flex;
	margin-bottom: 2rem;
	h1{
		font-size: $title-font;
	}
}

.icon{
	width: 30px;
	margin-right: 10px;
	filter: var(--background-invert-if-dark);
}

@media screen and (max-width: 700px) {
	.signed {
		width: 100%;
	}
	.infor-container {
		margin-right: 0px;
		width: 100%;
	}
	.infor-bg {
		box-shadow: none;
	}
	.container {
		align-items: flex-start;
	}
	.infor {
		h1 {
			font-size: $title-font-mobile;
		}
	}
	.header {
		h1 {
			font-size: $title-font-mobile;
		}
	}
}
</style>
