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
									<span>{{ formatData(document.createdAt) }}</span>
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
									<div class="subscriber-item">
										<h3>{{ t('libresign', 'Signed by:') }}</h3>
										<span>{{ getName(item) }}</span>
									</div>
									<div class="subscriber-item">
										<h3>{{ t('libresign', 'At:') }}</h3>
										<span>{{ item.signed ? formatData(item.signed) : t('libresign', 'Pending signin') }}</span>
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
import { fromUnixTime, format } from 'date-fns'
import { loadState } from '@nextcloud/initial-state'

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
		console.info(JSON.parse(loadState('libresign', 'config')))
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
				return format(fromUnixTime(data), 'dd/MM/yyyy HH:mm')
			} catch {
				return t('libresign', 'No date')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
$text-color: #273849;
$background: #ECEFFC;
$title-font: 1.5rem;
$title-font-mobile: 1.3rem;
$date-signed-font: .7rem;

.btn-load {
	background-color: transparent !important;
	font-size: 0;
	pointer-events: none;
	cursor: not-allowed;
	margin-top: 10px;
	border: none;
}

.jumbotron{
	background-color: $background;
	padding: 0;
}

.container{
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100%;

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
		max-height: 100px;
		overflow: scroll;
	}
}

form{
	background-color: #FFF;
	color: $text-color;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 20px;
	margin: 20px;
	border-radius: 8px;
	border: 1px solid var(--color-background-dark);
	max-width: 500px;
	box-shadow: rgba(17, 12, 46, 0.15) 0px 48px 100px 0px;

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
	color: $text-color;
}

h3{
	color: #337ab7;
}

input{
	width: 100%;
	margin: 20px 0px;
	background-color: var(--color-background-dark);
}

button{
	background-color: #0082c9;
	color: #FFF;
	float: right;
	margin-top: 20px;
	&:hover{
		background-color: #286090;
	}
}

.infor{
	display: flex;
	flex-direction: column;
	h1{
		font-size: $title-font;
	}
}

.infor-container{
	margin-right: 20px;
}

.infor-bg{

	.infor-header{
		display: flex;
		flex-direction: column;
		width: 700px;
		height: 300px;
		margin-top: 10px;
		background-color: #fff;
		box-shadow: rgba(17, 12, 46, 0.15) 0px 48px 100px 0px;
		border-radius: 8px;
		padding: 20px 60px 20px 20px;
		min-height: 415px;

		.line{
			display: flex;
			flex-direction: row;
			width: 100%;
			margin: 15px 0;

			.line-group{
				display: flex;
				flex-direction: column;
				line-height: 10px;
				width: 100%;

				h3{
					font-weight: 400;
					color: #000;
					min-width: 200px;
				}

				span{
					font-size: 16px;
					color: rgba(0, 0, 0, 0.7);
				}
			}
		}
		.button{
			align-self: flex-end;
		}
	}

	.infor-content{
		display: flex;
		flex-direction: column;
		overflow: scroll;
		height: 80%;
		width: 95%;
		margin: 0 20px;
	}
}

.info-document{
	color: $text-color;
	display: flex;
	flex-direction: column;
	width: 100%;
	margin-left: 30px;
	max-height: 250px;
	justify-content: center;
	overflow: scroll;

	p{
		font-size: 1rem;
	}
	a{
		width: fit-content;
		align-self: flex-end;
	}
	#sign {
		display: flex;
	}
}

.signed {
	width: 700px;
	height: 300px;
	margin-top: 10px;
	background-color: #FFFFFF;
	padding: 20px 60px 20px 20px;
	border-radius: 8px;
	box-shadow: rgba(17, 12, 46, 0.15) 0px 48px 100px 0px;

	strong {
		font-size: 22px;
		margin-bottom: 10px;
	}
	button {
		float: right;
	}
}

.scroll {
	display: flex;
	flex-direction: row;
	min-width: 200px;
	background-color: $background;
	border-radius: 8px;
	padding: 0 10px 10px 10px;
	margin: 5px;

	.subscriber-item{
		width: 40%;
		line-height: 12px;
		overflow: hidden;
		text-overflow: ellipsis;
		padding: 10px;

		span{
			opacity: .8;
		}
		h3{
			color: #000000;
		}
	}

	@media (max-width: 600px) {
		flex-direction: column;

		.subscriber-item{
			width: 100%;
		}
	}
}

.subscriber {
	display: flex;
	flex-direction: column;
	color: $text-color;
	background-color: $background;
	border-radius: 8px;
	padding: 5px 0px 5px 5px;
	margin: 5px 5px 0px 0px;
	min-height: 50px;
	width: 100%;
	max-width: 98%;

	.subscriber-item{
		margin-right: 20px;
		h3{
			font-weight: bold;
		}
		span{
			opacity: .8;
		}
	}

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
	h1{
		font-size: $title-font;
	}
	span{
		margin-top: 20px;
	}
}

.icon{
	width: 30px;
	margin-right: 10px;
}

@media screen and (max-width: 700px) {
	.infor-container {
		margin-right: 0px;
		width: 100%;
	}
	.infor-bg {
		box-shadow: none;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;

		.infor-header{
			width: 95%;

			.line-group{
				line-height: 18px;
			}
		}

		.signed{
			width: 95%;
			box-shadow: none;
		}
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

@media (max-width: 400px) {
	.infor-bg{

		.infor-header{
			height: 520px;
		}

		.line{
			flex-direction: column !important;

			.line-group{
				span{
					line-height: 18px;
				}
			}
		}
	}
}

#legal-information {
	margin-right: 0;
	span{
		width: 100%;
		line-height: normal;
	}
}

</style>
