<template>
	<div v-if="filesStore.identifyingSigner"
		id="request-signature-identify-signer">
		<IdentifySigner :signer-to-edit="signerToEdit" />
	</div>
	<div v-else
		id="request-signature-list-signers">
		<NcButton v-if="canRequestSign && !filesStore.isFullSigned()"
			:type="hasSigners ? 'secondary' : 'primary'"
			@click="addSigner">
			{{ t('libresign', 'Add signer') }}
		</NcButton>
		<Signers :signers="dataSigners"
			event="libresign:edit-signer">
			<template #actions="{signer}">
				<NcActionButton v-if="canRequestSign && !signer.signed"
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
			<NcButton v-if="filesStore.isFullSigned()"
				type="primary"
				@click="validationFile()">
				{{ t('libresign', 'Validate') }}
			</NcButton>
		</div>
		<VisibleElements />
		<NcModal v-if="showSignModal" size="full" @close="closeModal()">
			<iframe :src="modalSrc" class="iframe" />
		</NcModal>
	</div>
</template>
<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import { getCurrentUser } from '@nextcloud/auth'
import Delete from 'vue-material-design-icons/Delete.vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { showResponseError } from '../../helpers/errors.js'
import Signers from '../Signers/Signers.vue'
import IdentifySigner from './IdentifySigner.vue'
import VisibleElements from './VisibleElements.vue'
import { loadState } from '@nextcloud/initial-state'
import { useFilesStore } from '../../store/files.js'

export default {
	name: 'RequestSignature',
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
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
	data() {
		return {
			hasLoading: false,
			signerToEdit: {},
			modalSrc: '',
			showSignModal: false,
			canRequestSign: loadState('libresign', 'can_request_sign', false),
		}
	},
	computed: {
		canSave() {
			return this.canRequestSign
				&& (
					!Object.hasOwn(this.filesStore.getFile(), 'requested_by')
					|| this.filesStore.getFile().requested_by.uid === getCurrentUser().uid
				)
				&& !this.filesStore.isPartialSigned()
				&& !this.filesStore.isFullSigned()
				&& this.filesStore.getFile()?.signers?.length > 0
		},
		canSign() {
			return !this.filesStore.isFullSigned()
				&& this.filesStore.getFile().status > 0
				&& this.filesStore.getFile()?.signers?.filter(signer => signer.me).length > 0
		},
		dataSigners() {
			return this.filesStore.files[this.filesStore.selectedNodeId].signers
		},
		hasSigners() {
			return this.filesStore.hasSigners()
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
			this.showSignModal = false
		},
		validationFile() {
			this.$router.push({ name: 'validationFile', params: { uuid: this.filesStore.getFile().uuid } })
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
			try {
				const body = {
					fileId: this.filesStore.selectedNodeId,
					signRequestId: signer.signRequestId,
				}

				const response = await axios.post(generateOcsUrl('/apps/libresign/api/v1/notify/signer'), body)
				showSuccess(t('libresign', response.data.message))
			} catch (err) {
				if (err.response) {
					return showResponseError(err.response)
				}
				return showError(err.message)
			}

		},
		async sign() {
			const uuid = this.filesStore.getFile().signers
				.reduce((accumulator, signer) => {
					if (signer.me) {
						return signer.sign_uuid
					}
					return accumulator
				}, '')
			const route = this.$router.resolve({ name: 'SignPDF', params: { uuid } })
			this.modalSrc = route.href
			this.showSignModal = true
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
					this.filesStore.addFile(data.data)
					emit('libresign:show-visible-elements')
				})
				.catch(({ response }) => {
					if (response.data.message) {
						showError(response.data.message)
					} else if (response.data.errors) {
						response.data.errors.forEach(error => showError(error))
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
