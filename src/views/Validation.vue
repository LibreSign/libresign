<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container">
		<div class="logo">
			<img :src="logo" draggable="false">
		</div>
		<div id="dataUUID">
			<form v-show="!hasInfo" @submit="(e) => e.preventDefault()">
				<h1>{{ t('libresign', 'Validate signature') }}</h1>
				<NcTextField v-model="uuidToValidate" :label="t('libresign', 'Enter the ID or UUID of the document to validate.')" />
				<NcButton type="primary"
					:disabled="loading"
					@click.prevent="clickedValidate = true;validate(uuidToValidate)">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
					</template>
					{{ t('libresign', 'Validation') }}
				</NcButton>
			</form>
			<div v-if="hasInfo" class="infor-container">
				<div class="section">
					<div class="header">
						<NcIconSvgWrapper :path="mdiInformationSlabCircle" :size="30" />
						<h1>{{ t('libresign', 'Document informations') }}</h1>
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
								<NcLoadingIcon v-if="loading" :size="20" />
							</template>
							{{ t('libresign', 'View') }}
						</NcButton>
					</div>
				</div>
				<div class="section">
					<div class="header">
						<NcIconSvgWrapper :path="mdiSignatureFreehand" :size="30" />
						<h1>{{ t('libresign', 'Signatories:') }}</h1>
					</div>
					<ul>
						<span v-for="(signer, signerIndex) in document.signers"
							:key="signerIndex">
							<NcListItem :name="getName(signer)"
								:active="signer.opened"
								@click="toggleDetail(signer)">
								<template #icon>
									<NcAvatar disable-menu
										:is-no-user="!signer.userId"
										:size="44"
										:user="signer.userId ? signer.userId : getName(signer)"
										:display-name="getName(signer)" />
								</template>
								<template #subname>
									<strong>{{ t('LibreSign', 'Date signed:') }}</strong>
									<span v-if="signer.signed" class="data-signed">
										{{ signer.signed }}
									</span>
									<span v-else>{{ t('libresign', 'No date') }}</span>
								</template>
								<template #extra-actions>
									<NcButton type="tertiary"
										@click="toggleDetail(signer)">
										<template #icon>
											<NcIconSvgWrapper v-if="signer.opened"
												:path="mdiUnfoldLessHorizontal"
												:size="20" />
											<NcIconSvgWrapper v-else
												:path="mdiUnfoldMoreHorizontal"
												:size="20" />
										</template>
									</NcButton>
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened"
								class="extra"
								compact
								:name="t('libresign', 'Requested on:')">
								<template #name>
									<strong>{{ t('libresign', 'Requested on:') }}</strong>
									{{ dateFromSqlAnsi(signer.request_sign_date) }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.remote_address"
								class="extra"
								compact
								:name="t('libresign', 'Remote address:')">
								<template #name>
									<strong>{{ t('libresign', 'Remote address:') }}</strong>
									{{ signer.remote_address }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.user_agent"
								class="extra"
								compact
								:name="t('libresign', 'User agent:')">
								<template #name>
									<strong>{{ t('libresign', 'User agent:') }}</strong>
									{{ signer.user_agent }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.notify"
								class="extra"
								compact
								:name="t('libresign', 'Notifications:')">
								<template #name>
									<strong>{{ t('libresign', 'Notifications:') }}</strong>
								</template>
								<template #subname>
									<ul>
										<li v-for="(notify, notifyIndex) in signer.notify"
											:key="notifyIndex">
											<strong>{{ notify.method }}</strong>: {{ dateFromUnixTimestamp(notify.date) }}
										</li>
									</ul>
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.valid_from"
								class="extra"
								compact
								:name="t('libresign', 'Certificate valid from:')">
								<template #name>
									<strong>{{ t('libresign', 'Certificate valid from:') }}</strong>
									{{ dateFromUnixTimestamp(signer.valid_from) }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.valid_to"
								class="extra"
								compact
								:name="t('libresign', 'Certificate valid to:')">
								<template #name>
									<strong>{{ t('libresign', 'Certificate valid to:') }}</strong>
									{{ dateFromUnixTimestamp(signer.valid_to) }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.subject"
								class="extra"
								compact
								:name="t('libresign', 'Subject:')">
								<template #name>
									<strong>{{ t('libresign', 'Subject:') }}</strong>
									{{ signer.subject }}
								</template>
							</NcListItem>
						</span>
					</ul>
				</div>
				<NcButton v-if="clickedValidate"
					type="primary"
					@click.prevent="goBack">
					{{ t('libresign', 'Return') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import {
	mdiInformationSlabCircle,
	mdiSignatureFreehand,
	mdiUnfoldMoreHorizontal,
	mdiUnfoldLessHorizontal,
} from '@mdi/js'
import JSConfetti from 'js-confetti'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import Moment from '@nextcloud/moment'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
// eslint-disable-next-line import/no-named-as-default
import NcRichText from '@nextcloud/vue/dist/Components/NcRichText.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import logoGray from '../../img/logo-gray.svg'
import logger from '../logger.js'

export default {
	name: 'Validation',

	components: {
		NcTextField,
		NcRichText,
		NcButton,
		NcLoadingIcon,
		NcListItem,
		NcAvatar,
		NcIconSvgWrapper,
		NcNoteCard,
	},

	data() {
		return {
			logo: logoGray,
			mdiUnfoldMoreHorizontal,
			mdiInformationSlabCircle,
			mdiUnfoldLessHorizontal,
			mdiSignatureFreehand,
			uuidToValidate: this.$route.params?.uuid ?? '',
			hasInfo: false,
			loading: false,
			document: {},
			legalInformation: loadState('libresign', 'legal_information', ''),
			clickedValidate: false,
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
		if (this.uuidToValidate.length > 0) {
			this.validate(this.uuidToValidate)
		} else {
			this.$set(this, 'document', loadState('libresign', 'file_info', {}))
			this.hasInfo = Object.keys(this.document).length > 0
			if (this.hasInfo) {
				this.document.signers.forEach(signer => {
					this.$set(signer, 'opened', false)
				})
			}
		}
	},
	methods: {
		dateFromSqlAnsi(date) {
			return Moment(Date.parse(date)).format('LL LTS')
		},
		dateFromUnixTimestamp(date) {
			return Moment(date * 1000).format('LL LTS')
		},
		toggleDetail(signer) {
			this.$set(signer, 'opened', !signer.opened)
		},
		validate(id) {
			if (id === this.document?.uuid) {
				showSuccess(t('libresign', 'This document is valid'))
				this.hasInfo = true
				return
			}
			if (id.length === 36) {
				this.validateByUUID(id)
			} else {
				this.validateByNodeID(id)
			}
		},
		async validateByUUID(uuid) {
			this.loading = true
			try {
				const response = await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/uuid/${uuid}`))
				showSuccess(t('libresign', 'This document is valid'))
				this.$set(this, 'document', response.data.ocs.data)
				this.document.signers.forEach(signer => {
					this.$set(signer, 'opened', false)
				})
				this.hasInfo = true
				this.loading = false
				if (this.isAfterSigned) {
					const jsConfetti = new JSConfetti()
					jsConfetti.addConfetti()
				}
			} catch (err) {
				this.loading = false
				showError(err.response.data.ocs.data.errors[0])
			}
		},
		async validateByNodeID(nodeId) {
			this.loading = true
			try {
				const response = await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${nodeId}`))
				showSuccess(t('libresign', 'This document is valid'))
				this.$set(this, 'document', response.data.ocs.data)
				this.hasInfo = true
				this.loading = false
				if (this.isAfterSigned) {
					const jsConfetti = new JSConfetti()
					jsConfetti.addConfetti()
				}
			} catch (err) {
				this.loading = false
				showError(err.response.data.ocs.data.errors[0])
			}
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
			this.uuidToValidate = this.$route.params.uuid
		},
	},
}
</script>

<style lang="scss" scoped>
.container {
	display: flex;
	align-items: center;
	justify-content: center;
	overflow-y: auto;
	width: 100%;
	height: 100%;

	.logo {
		width: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
		img {
			width: 50%;
			max-width: 422px;
		}
		@media screen and (max-width: 900px) {
			display: none;
			width: 0%;
		}

	}
	#dataUUID {
		width: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
		@media screen and (max-width: 900px){
			width: 100%;
		}
		h1 {
			font-size: 24px;
			font-weight: bold;
			color: var(--color-main-text);
		}
		form {
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
		button {
			float: right;
			margin-top: 20px;
			align-self: flex-end;
		}
		.infor-container {
			width: 100%;
			margin-right: 20px;
			.section {
				background-color: var(--color-main-background);
				padding: 20px;
				border-radius: 8px;
				box-shadow: 0 0 6px 0 var(--color-box-shadow);
				margin-bottom: 10px;

				.header {
					display: flex;
					margin-bottom: 2rem;
				}
				h1 {
					font-size: 1.5rem;
				}

				.info-document {
					color: var(--color-main-text);
					display: flex;
					flex-direction: column;
					overflow: scroll;
					.legal-information {
						opacity: 0.8;
						align-self: center;
						font-size: 1rem;
						overflow: scroll;
					}

					p {
						font-size: 1rem;
					}
				}

				.extra {
					margin-left: 44px;
					padding-right: 44px;
					:deep(.list-item-content__name) {
						white-space: unset;
					}
					:deep(.list-item__anchor) {
						height: unset;
					}
				}
			}
		}
	}
}

@media screen and (max-width: 700px) {
	.container {
		align-items: flex-start;
		h1 {
			font-size: 1.3rem;
		}
		.infor-container {
			margin-right: 0px;
			.section {
				box-shadow: none;
			}
		}
	}
}
</style>
