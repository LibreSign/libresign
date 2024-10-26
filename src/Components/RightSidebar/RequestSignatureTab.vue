<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="filesStore.identifyingSigner"
		id="request-signature-identify-signer">
		<IdentifySigner :signer-to-edit="signerToEdit" />
	</div>
	<div v-else
		id="request-signature-tab">
		<NcButton v-if="canAddSigner"
			:type="hasSigners ? 'secondary' : 'primary'"
			@click="addSigner">
			{{ t('libresign', 'Add signer') }}
		</NcButton>
		<Signers :signers="dataSigners"
			event="libresign:edit-signer">
			<template #actions="{signer}">
				<NcActionButton v-if="canSave && !signer.signed"
					aria-label="Delete"
					:close-after-click="true"
					@click="filesStore.deleteSigner(signer)">
					<template #icon>
						<Delete :size="20" />
					</template>
					{{ t('libresign', 'Delete') }}
				</NcActionButton>
				<NcActionButton v-if="canRequestSign && !signer.signed && signer.signRequestId && !signer.me"
					icon="icon-comment"
					:close-after-click="true"
					@click="sendNotify(signer)">
					{{ t('libresign', 'Send reminder') }}
				</NcActionButton>
			</template>
		</Signers>
		<div class="action-buttons">
			<NcButton v-if="canSave"
				:type="canSign ? 'secondary' : 'primary'"
				:disabled="hasLoading"
				@click="save()">
				<template #icon>
					<NcLoadingIcon v-if="hasLoading" :size="20" />
				</template>
				{{ t('libresign', 'Next') }}
			</NcButton>
			<NcButton v-if="canSign"
				type="primary"
				:disabled="hasLoading"
				@click="sign()">
				<template #icon>
					<NcLoadingIcon v-if="hasLoading" :size="20" />
				</template>
				{{ t('libresign', 'Sign') }}
			</NcButton>
			<NcButton v-if="canValidate"
				type="primary"
				@click="validationFile()">
				{{ t('libresign', 'Validate') }}
			</NcButton>
		</div>
		<VisibleElements />
		<NcModal v-if="modalSrc"
			size="full"
			:name="fileName"
			:close-button-contained="false"
			@close="closeModal()">
			<iframe :src="modalSrc" class="iframe" />
		</NcModal>
	</div>
</template>
<script>
import Delete from 'vue-material-design-icons/Delete.vue'

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import IdentifySigner from '../Request/IdentifySigner.vue'
import VisibleElements from '../Request/VisibleElements.vue'
import Signers from '../Signers/Signers.vue'

import router from '../../router/router.js'
import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'

export default {
	name: 'RequestSignatureTab',
	components: {
		NcActionButton,
		NcButton,
		NcLoadingIcon,
		NcModal,
		Delete,
		Signers,
		IdentifySigner,
		VisibleElements,
	},
	props: {
		useModal: {
			type: Boolean,
			default: false,
		},
	},
	setup() {
		const filesStore = useFilesStore()
		const signStore = useSignStore()
		const sidebarStore = useSidebarStore()
		return { filesStore, signStore, sidebarStore }
	},
	data() {
		return {
			hasLoading: false,
			signerToEdit: {},
			modalSrc: '',
			canRequestSign: loadState('libresign', 'can_request_sign', false),
		}
	},
	computed: {
		canAddSigner() {
			return this.canRequestSign
				&& (
					!Object.hasOwn(this.filesStore.getFile(), 'requested_by')
					|| this.filesStore.getFile().requested_by.userId === getCurrentUser().uid
				)
				&& !this.filesStore.isPartialSigned()
				&& !this.filesStore.isFullSigned()
		},
		canSave() {
			return this.canRequestSign
				&& (
					!Object.hasOwn(this.filesStore.getFile(), 'requested_by')
					|| this.filesStore.getFile().requested_by.userId === getCurrentUser().uid
				)
				&& !this.filesStore.isPartialSigned()
				&& !this.filesStore.isFullSigned()
				&& this.filesStore.getFile()?.signers?.length > 0
		},
		canSign() {
			return !this.filesStore.isFullSigned()
				&& this.filesStore.getFile().status > 0
				&& this.filesStore.getFile()?.signers?.filter(signer => signer.me).length > 0
				&& this.filesStore.getFile()?.signers?.filter(signer => signer.me)
					.filter(signer => signer.signed?.length > 0).length === 0
		},
		canValidate() {
			return this.filesStore.isPartialSigned()
				|| this.filesStore.isFullSigned()
		},
		dataSigners() {
			return this.filesStore.getFile()?.signers ?? []
		},
		hasSigners() {
			return this.filesStore.hasSigners()
		},
		fileName() {
			return this.filesStore.getFile()?.name ?? ''
		},
	},
	watch: {
		signers(signers) {
			this.init(signers)
		},
	},
	async mounted() {
		subscribe('libresign:edit-signer', this.editSigner)
		this.filesStore.disableIdentifySigner()
	},
	beforeUnmount() {
		unsubscribe('libresign:edit-signer')
	},
	methods: {
		closeModal() {
			this.modalSrc = ''
			this.filesStore.flushSelectedFile()
		},
		validationFile() {
			if (this.useModal) {
				const route = router.resolve({ name: 'ValidationFileExternal', params: { uuid: this.filesStore.getFile().uuid } })
				this.modalSrc = route.href
				return
			}
			this.$router.push({ name: 'ValidationFile', params: { uuid: this.filesStore.getFile().uuid } })
			this.sidebarStore.hideSidebar()
		},
		addSigner() {
			this.signerToEdit = {}
			this.filesStore.enableIdentifySigner()
		},
		editSigner(signer) {
			this.signerToEdit = signer
			this.filesStore.enableIdentifySigner()
		},
		async sendNotify(signer) {
			const body = {
				fileId: this.filesStore.selectedNodeId,
				signRequestId: signer.signRequestId,
			}

			await axios.post(generateOcsUrl('/apps/libresign/api/v1/notify/signer'), body)
				.then(({ data }) => {
					showSuccess(t('libresign', data.ocs.data.message))
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
				})

		},
		async sign() {
			const uuid = this.filesStore.getFile().signers
				.reduce((accumulator, signer) => {
					if (signer.me) {
						return signer.sign_uuid
					}
					return accumulator
				}, '')
			if (this.useModal) {
				const route = router.resolve({ name: 'SignPDFExternal', params: { uuid } })
				this.modalSrc = route.href
				return
			}
			this.signStore.setDocumentToSign(this.filesStore.getFile())
			this.$router.push({ name: 'SignPDF', params: { uuid } })
		},
		async save() {
			this.hasLoading = true
			const config = {
				url: generateOcsUrl('/apps/libresign/api/v1/request-signature'),
				data: {
					status: this.filesStore.getFile()?.status ?? 0,
					name: this.filesStore.getFile()?.name,
					users: [],
				},
			}
			this.dataSigners.forEach(signer => {
				const user = {
					displayName: signer.displayName,
					identify: {},
				}
				signer.identifyMethods.forEach(method => {
					user.notify = false
					if (method.method === 'account') {
						user.identify.account = method?.value?.id ?? method?.value ?? signer.uid
					} else if (method.method === 'email') {
						user.identify.email = method?.value?.id ?? method?.value ?? signer.email
					}
				})
				config.data.users.push(user)
			})

			if (this.filesStore.getFile().uuid) {
				config.data.uuid = this.filesStore.getFile().uuid
				config.method = 'patch'
			} else {
				config.data.file = {
					fileId: this.filesStore.selectedNodeId,
				}
				config.method = 'post'
			}
			await axios(config)
				.then(({ data }) => {
					this.filesStore.addFile(data.ocs.data.data)
					emit('libresign:show-visible-elements')
				})
				.catch(({ response }) => {
					if (response.data.ocs.data.message) {
						showError(response.data.ocs.data.message)
					} else if (response.data.ocs.data.errors) {
						response.data.ocs.data.errors.forEach(error => showError(error))
					}
				})
			this.hasLoading = false
		},
	},
}
</script>
<style lang="scss" scoped>

.action-buttons{
	display: flex;
	box-sizing: border-box;
	grid-gap: 10px;
}

.iframe {
	width: 100%;
	height: 100%;
}
</style>
