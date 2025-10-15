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
			<div v-show="!hasInfo" class="infor-container">
				<div class="section">
					<h1>{{ t('libresign', 'Validate signature') }}</h1>
					<NcActions :menu-name="t('libresign', 'Validate signature')"
						:inline="3"
						:force-name="true">
						<NcActionButton :wide="true"
							:disabled="loading"
							@click="getUUID = true">
							{{ t('libresign', 'From UUID') }}
							<template #icon>
								<NcLoadingIcon v-if="loading" :size="20" />
								<NcIconSvgWrapper v-else :path="mdiKey" />
							</template>
						</NcActionButton>
						<NcActionButton :wide="true"
							:disabled="loading"
							@click="uploadFile">
							{{ t('libresign', 'Upload') }}
							<template #icon>
								<NcLoadingIcon v-if="loading" :size="20" />
								<NcIconSvgWrapper v-else :path="mdiUpload" />
							</template>
						</NcActionButton>
					</NcActions>
					<NcDialog v-if="getUUID"
						:name="t('libresign', 'Validate signature')"
						is-form
						@closing="getUUID = false">
						<h1>{{ t('libresign', 'Validate signature') }}</h1>
						<NcTextField v-model="uuidToValidate"
							:label="t('libresign', 'Enter the ID or UUID of the document to validate.')"
							:helper-text="helperTextValidation"
							:error="uuidToValidate.length > 0 && !canValidate" />
						<template #actions>
							<NcButton variant="primary"
								:disabled="loading || !canValidate"
								@click.prevent="clickedValidate = true;validate(uuidToValidate)">
								<template #icon>
									<NcLoadingIcon v-if="loading" :size="20" />
								</template>
								{{ t('libresign', 'Validation') }}
							</NcButton>
						</template>
					</NcDialog>
				</div>
			</div>
			<div v-if="hasInfo" class="infor-container">
				<div class="section">
					<div class="header">
						<NcIconSvgWrapper :path="mdiInformationSlabCircle" :size="30" />
						<h1>{{ t('libresign', 'Document informations') }}</h1>
					</div>
					<NcNoteCard v-if="isAfterSigned" type="success">
						{{ t('libresign', 'Congratulations you have digitally signed a document using LibreSign') }}
					</NcNoteCard>
					<ul>
						<NcListItem class="extra"
							compact
							:name="t('libresign', 'Name:')">
							<template #name>
								<strong>{{ t('libresign', 'Name:') }}</strong>
								{{ document.name }}
							</template>
						</NcListItem>
						<NcListItem v-if="document.status"
							class="extra"
							compact
							:name="t('libresign', 'Status:')">
							<template #name>
								<strong>{{ t('libresign', 'Status:') }}</strong>
								{{ documentStatus }}
							</template>
						</NcListItem>
						<NcListItem class="extra"
							compact
							:name="t('libresign', 'Total pages:')">
							<template #name>
								<strong>{{ t('libresign', 'Total pages:') }}</strong>
								{{ document.totalPages }}
							</template>
						</NcListItem>
						<NcListItem class="extra"
							compact
							:name="t('libresign', 'File size:')">
							<template #name>
								<strong>{{ t('libresign', 'File size:') }}</strong>
								{{ size }}
							</template>
						</NcListItem>
						<NcListItem class="extra"
							compact
							:name="t('libresign', 'PDF version:')">
							<template #name>
								<strong>{{ t('libresign', 'PDF version:') }}</strong>
								{{ document.pdfVersion }}
							</template>
						</NcListItem>
					</ul>
					<div class="info-document">
						<NcRichText class="legal-information"
							:text="legalInformation"
							:use-markdown="true" />

						<NcButton variant="primary"
							@click="viewDocument()">
							<template #icon>
								<NcLoadingIcon v-if="loading" :size="20" />
							</template>
							{{ t('libresign', 'View') }}
						</NcButton>
					</div>
				</div>
				<div v-if="document.signers" class="section">
					<div class="header">
						<NcIconSvgWrapper :path="mdiSignatureFreehand" :size="30" />
						<h1>{{ t('libresign', 'Signatories:') }}</h1>
					</div>
					<ul class="signers">
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
									<strong>{{ t('libresign', 'Date signed:') }}</strong>
									<span v-if="signer.signed" class="data-signed">
										{{ dateFromSqlAnsi(signer.signed) }}
									</span>
									<span v-else>{{ t('libresign', 'No date') }}</span>
								</template>
								<template #extra-actions>
									<NcButton variant="tertiary"
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
								<template #indicator>
									<NcIconSvgWrapper v-if="signer.signature_validation"
										:name="signer.signature_validation.label"
										:path="getIconValidityPath(signer)"
										:style="{color: signer.signature_validation?.id === 1? 'green' : 'red'}"
										:size="20" />
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.request_sign_date"
								class="extra"
								compact
								:name="t('libresign', 'Requested on:')">
								<template #name>
									<strong>{{ t('libresign', 'Requested on:') }}</strong>
									{{ dateFromSqlAnsi(signer.request_sign_date) }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.signature_validation"
								class="extra"
								compact
								:name="t('libresign', 'Signature validation:')">
								<template #name>
									<strong>{{ t('libresign', 'Signature validation:') }}</strong>
									{{ signer.signature_validation.label }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.certificate_validation"
								class="extra"
								compact
								:name="t('libresign', 'Certificate validation:')">
								<template #name>
									<strong>{{ t('libresign', 'Certificate validation:') }}</strong>
									{{ signer.certificate_validation.label }}
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
											<strong>{{ notify.method }}</strong>: {{ dateFromSqlAnsi(notify.date) }}
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
									{{ dateFromSqlAnsi(signer.valid_from) }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.valid_to"
								class="extra"
								compact
								:name="t('libresign', 'Certificate valid to:')">
								<template #name>
									<strong>{{ t('libresign', 'Certificate valid to:') }}</strong>
									{{ dateFromSqlAnsi(signer.valid_to) }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.hash_algorithm"
								class="extra"
								compact
								:name="t('libresign', 'Hash algorithm:')">
								<template #name>
									<strong>{{ t('libresign', 'Hash algorithm:') }}</strong>
									{{ signer.hash_algorithm }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.timestamp.displayName"
								class="extra"
								compact
								:name="t('libresign', 'Timestamp Authority:')">
								<template #name>
									<strong>{{ t('libresign', 'Timestamp Authority:') }}</strong>
									{{ signer.timestamp.displayName }}
								</template>
							</NcListItem>
							<div v-if="signer.opened && signer.timestamp.displayName">
								<NcListItem class="extra-chain"
									compact
									:name="t('libresign', 'Time:')">
									<template #name>
										<strong>{{ t('libresign', 'Time:') }}</strong>
										{{ dateFromSqlAnsi(signer.timestamp.genTime) }}
									</template>
								</NcListItem>
								<NcListItem v-if="signer.timestamp.policy"
									class="extra-chain"
									compact
									:name="t('libresign', 'TSA Policy:')">
									<template #name>
										<strong>{{ t('libresign', 'TSA Policy:') }}</strong>
										<span v-if="signer.timestamp.policyName">
											{{ signer.timestamp.policyName }} ({{ signer.timestamp.policy }})
										</span>
										<span v-else>
											{{ signer.timestamp.policy }}
										</span>
									</template>
								</NcListItem>
								<NcListItem v-if="signer.timestamp.serialNumber"
									class="extra-chain"
									compact
									:name="t('libresign', 'TSA Serial:')">
									<template #name>
										<strong>{{ t('libresign', 'TSA Serial:') }}</strong>
										{{ signer.timestamp.serialNumber }}
									</template>
								</NcListItem>
								<NcListItem v-if="signer.timestamp.hashAlgorithm"
									class="extra-chain"
									compact
									:name="t('libresign', 'TSA Hash Algorithm:')">
									<template #name>
										<strong>{{ t('libresign', 'TSA Hash Algorithm:') }}</strong>
										<span>{{ signer.timestamp.hashAlgorithm || signer.timestamp.hashAlgorithmOID }}</span>
										<span v-if="signer.timestamp.hashAlgorithmOID && signer.timestamp.hashAlgorithm && signer.timestamp.hashAlgorithm !== signer.timestamp.hashAlgorithmOID">
											({{ signer.timestamp.hashAlgorithmOID }})
										</span>
									</template>
								</NcListItem>
								<NcListItem v-if="signer.timestamp.accuracy && (signer.timestamp.accuracy.seconds || signer.timestamp.accuracy.millis || signer.timestamp.accuracy.micros)"
									class="extra-chain"
									compact
									:name="t('libresign', 'TSA Accuracy:')">
									<template #name>
										<strong>{{ t('libresign', 'TSA Accuracy:') }}</strong>
										<span v-if="signer.timestamp.accuracy.seconds">{{ signer.timestamp.accuracy.seconds }}s</span>
										<span v-if="signer.timestamp.accuracy.millis"> {{ signer.timestamp.accuracy.millis }}ms</span>
										<span v-if="signer.timestamp.accuracy.micros"> {{ signer.timestamp.accuracy.micros }}μs</span>
									</template>
								</NcListItem>
								<NcListItem v-if="signer.timestamp.ordering !== undefined"
									class="extra-chain"
									compact
									:name="t('libresign', 'TSA Ordering:')">
									<template #name>
										<strong>{{ t('libresign', 'TSA Ordering:') }}</strong>
										{{ signer.timestamp.ordering ? t('libresign', 'Yes') : t('libresign', 'No') }}
									</template>
								</NcListItem>
								<NcListItem v-if="signer.timestamp.nonce"
									class="extra-chain"
									compact
									:name="t('libresign', 'TSA Nonce:')">
									<template #name>
										<strong>{{ t('libresign', 'TSA Nonce:') }}</strong>
										{{ signer.timestamp.nonce }}
									</template>
								</NcListItem>
								<div v-if="signer.timestamp.chain && signer.timestamp.chain.length > 0">
									<NcListItem class="extra-chain"
										compact
										:name="t('libresign', 'TSA Certificate Chain:')">
										<template #name>
											<strong>{{ t('libresign', 'TSA Certificate Chain:') }}</strong>
										</template>
									</NcListItem>
									<NcListItem v-for="(cert, certIndex) in signer.timestamp.chain"
										:key="`tsa-cert-${certIndex}`"
										class="extra-chain"
										compact>
										<template #name>
											<strong>{{ t('libresign', 'Subject:') }}</strong>
											{{ cert.name || cert.subject?.CN || t('libresign', 'Unknown') }}
											<br>
											<small>
												<strong>{{ t('libresign', 'Valid from:') }}</strong>
												{{ cert.validFrom_time_t ? new Date(cert.validFrom_time_t * 1000).toLocaleString() : t('libresign', 'Unknown') }}
												<br>
												<strong>{{ t('libresign', 'Valid to:') }}</strong>
												{{ cert.validTo_time_t ? new Date(cert.validTo_time_t * 1000).toLocaleString() : t('libresign', 'Unknown') }}
											</small>
										</template>
									</NcListItem>
								</div>
							</div>
							<NcListItem v-if="signer.opened && signer.subject"
								class="extra"
								compact
								:name="t('libresign', 'Certificate chain:')">
								<template #name>
									<strong>{{ t('libresign', 'Certificate chain:') }}</strong>
									{{ signer.subject }}
								</template>
							</NcListItem>
							<div v-if="signer.opened && signer.chain">
								<NcListItem v-for="(issuer, issuerIndex) in signer.chain"
									:key="issuerIndex"
									class="extra-chain"
									compact
									:name="t('libresign', 'Issuer:')">
									<template #name>
										<strong>{{ t('libresign', 'Issuer:') }}</strong>
										{{ issuer.displayName }}
									</template>
								</NcListItem>
							</div>
						</span>
					</ul>
				</div>
				<NcButton v-if="clickedValidate"
					variant="primary"
					@click.prevent="goBack">
					{{ t('libresign', 'Return') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import {
	mdiAlertCircle,
	mdiCheckboxMarkedCircle,
	mdiInformationSlabCircle,
	mdiKey,
	mdiSignatureFreehand,
	mdiUnfoldLessHorizontal,
	mdiUnfoldMoreHorizontal,
	mdiUpload,
} from '@mdi/js'
import JSConfetti from 'js-confetti'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { formatFileSize } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import Moment from '@nextcloud/moment'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
// eslint-disable-next-line import/no-named-as-default
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import logoGray from '../../img/logo-gray.svg'
import { fileStatus } from '../helpers/fileStatus.js'
import logger from '../logger.js'

export default {
	name: 'Validation',

	components: {
		NcActionButton,
		NcActions,
		NcAvatar,
		NcButton,
		NcDialog,
		NcIconSvgWrapper,
		NcListItem,
		NcLoadingIcon,
		NcNoteCard,
		NcRichText,
		NcTextField,
	},
	setup() {
		return {
			mdiAlertCircle,
			mdiCheckboxMarkedCircle,
			mdiInformationSlabCircle,
			mdiKey,
			mdiSignatureFreehand,
			mdiUnfoldLessHorizontal,
			mdiUnfoldMoreHorizontal,
			mdiUpload,
		}
	},
	data() {
		return {
			logo: logoGray,
			uuidToValidate: this.$route.params?.uuid ?? '',
			hasInfo: false,
			loading: false,
			document: {},
			legalInformation: loadState('libresign', 'legal_information', ''),
			clickedValidate: false,
			getUUID: false,
			getUploadedFile: false,
			urlQrCode: '',
		}
	},
	computed: {
		isAfterSigned() {
			return this.$route.params.isAfterSigned ?? false
		},
		canValidate() {
			const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
			return this.uuidToValidate.length === 36 && uuidRegex.test(this.uuidToValidate)
		},
		helperTextValidation() {
			if (this.uuidToValidate.length > 0 && !this.canValidate) {
				return t('libresign', 'Invalid UUID')
			}
			return ''
		},
		size() {
			return formatFileSize(this.document.size)
		},
		documentStatus() {
			const actual = fileStatus.find(item => item.id === this.document.status)
			if (actual === undefined) {
				return fileStatus.find(item => item.id === -1).label
			}
			return actual.label
		},
	},
	watch: {
		'$route.params.uuid'(uuid) {
			this.validate(uuid)
		},
	},
	created() {
		this.$set(this, 'document', loadState('libresign', 'file_info', {}))
		this.hasInfo = !!this.document?.name
		if (this.hasInfo) {
			this.document.signers.forEach(signer => {
				this.$set(signer, 'opened', false)
			})
		} else if (this.uuidToValidate.length > 0) {
			this.validate(this.uuidToValidate)
		}
	},
	methods: {
		async upload(file) {
			const formData = new FormData()
			formData.append('file', file)
			await axios.postForm(generateOcsUrl('/apps/libresign/api/v1/file/validate'), formData, {
				headers: {
					'Content-Type': 'multipart/form-data',
				},
			})
				.then(({ data }) => {
					this.clickedValidate = true
					showSuccess(t('libresign', 'This document is valid'))
					this.$set(this, 'document', data.ocs.data)
					this.document.signers?.forEach(signer => {
						this.$set(signer, 'opened', false)
					})
					this.hasInfo = true
					if (this.isAfterSigned) {
						const jsConfetti = new JSConfetti()
						jsConfetti.addConfetti()
					}
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.errors[0].message)
				})
		},
		async uploadFile() {
			this.loading = true
			const input = document.createElement('input')
			input.accept = 'application/pdf'
			input.type = 'file'

			input.onchange = async (ev) => {
				const file = ev.target.files[0]

				if (file) {
					await this.upload(file)
				}
				this.loading = false

				input.remove()
			}

			input.click()
		},
		dateFromSqlAnsi(date) {
			return Moment(Date.parse(date)).format('LL LTS')
		},
		toggleDetail(signer) {
			this.$set(signer, 'opened', !signer.opened)
		},
		validate(id) {
			if (id === this.document?.uuid) {
				showSuccess(t('libresign', 'This document is valid'))
				this.hasInfo = true
			} else if (id.length === 36) {
				this.validateByUUID(id)
			} else {
				this.validateByNodeID(id)
			}
			this.getUUID = false
		},
		async validateByUUID(uuid) {
			this.loading = true
			await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/uuid/${uuid}`))
				.then(({ data }) => {
					showSuccess(t('libresign', 'This document is valid'))
					this.$set(this, 'document', data.ocs.data)
					this.document.signers.forEach(signer => {
						this.$set(signer, 'opened', false)
					})
					this.hasInfo = true
					if (this.isAfterSigned) {
						const jsConfetti = new JSConfetti()
						jsConfetti.addConfetti()
					}
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.errors[0].message)
				})
			this.loading = false
		},
		async validateByNodeID(nodeId) {
			this.loading = true
			await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${nodeId}`))
				.then(({ data }) => {
					showSuccess(t('libresign', 'This document is valid'))
					this.$set(this, 'document', data.ocs.data)
					this.document.signers.forEach(signer => {
						this.$set(signer, 'opened', false)
					})
					this.hasInfo = true
					if (this.isAfterSigned) {
						const jsConfetti = new JSConfetti()
						jsConfetti.addConfetti()
					}
				})
				.catch(({ response }) => {
					if (response?.data?.ocs?.data?.errors?.length > 0) {
						showError(response.data.ocs.data.errors[0].message)
					}
				})
			this.loading = false
		},
		getName(signer) {
			if (signer.displayName) {
				return signer.displayName
			} else if (signer.email) {
				return signer.email
			}

			return signer.signature_validation.label
		},
		getIconValidityPath(signer) {
			if (signer.signature_validation.id === 1) {
				return mdiCheckboxMarkedCircle
			}
			return mdiAlertCircle
		},
		viewDocument() {
			if (OCA?.Viewer !== undefined) {
				const fileInfo = {
					source: this.document.file,
					basename: this.document.name,
					mime: 'application/pdf',
					fileid: this.document.nodeId,
				}
				OCA.Viewer.open({
					fileInfo,
					list: [fileInfo],
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
			float: inline-end;
			margin-top: 20px;
			align-self: flex-end;
		}
		.infor-container {
			width: 100%;
			margin: 20px;
			.section {
				background-color: var(--color-main-background);
				padding: 20px;
				border-radius: 8px;
				box-shadow: 0 0 6px 0 var(--color-box-shadow);
				margin-bottom: 10px;
				width: unset;
				overflow: hidden;
				@media screen and (max-width: 900px) {
					max-width: 100%;
				}
				.action-items {
					gap: 12px;
					flex-direction: column;
				}

				.header {
					display: flex;
					margin-bottom: 2rem;
				}
				h1 {
					font-size: 1.5rem;
				}

				.extra, .extra-chain {
					:deep(.list-item-content__name) {
						white-space: unset;
					}
					:deep(.list-item__anchor) {
						height: unset;
					}
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

				.signers {
					:deep(.list-item__wrapper) {
						box-sizing: border-box;
					}
					.extra {
						margin-inline-start: 44px;
						padding-inline-end: 44px;
					}
					.extra-chain {
						margin-inline-start: 88px;
						padding-inline-end: 88px;
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
			margin-inline-end: 0px;
			.section {
				width: unset;
				box-shadow: none;
			}
		}
	}
}
</style>
