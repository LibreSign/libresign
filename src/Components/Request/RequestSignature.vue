<template>
	<div v-if="filesStore.identifyingSigner"
		id="request-signature-identify-signer">
		<IdentifySigner :signer-to-edit="signerToEdit" />
	</div>
	<div v-else
		id="request-signature-list-signers">
		<NcButton v-if="canRequestSign && !filesStore.isSigned()"
			@click="addSigner">
			{{ t('libresign', 'Add signer') }}
		</NcButton>
		<Signers :signers="dataSigners"
			event="libresign:edit-signer">
			<template #actions="{signer}">
				<NcActionButton v-if="canRequestSign && !signer.sign_date"
					aria-label="Delete"
					:close-after-click="true"
					@click="filesStore.deleteSigner(signer)">
					<template #icon>
						<Delete :size="20" />
					</template>
					{{ t('libresign', 'Delete') }}
				</NcActionButton>
				<NcActionButton v-if="canRequestSign && !signer.sign_date && signer.signRequestId && !signer.me"
					icon="icon-comment"
					:close-after-click="true"
					@click="sendNotify(signer)">
					{{ t('libresign', 'Send reminder') }}
				</NcActionButton>
			</template>
		</Signers>
		<NcButton v-if="canSave"
			type="primary"
			@click="save()">
			{{ t('libresign', 'Next') }}
		</NcButton>
		<NcButton v-if="filesStore.isSigned()"
			@click="validationFile()">
			{{ t('libresign', 'Validate') }}
		</NcButton>
		<VisibleElements />
	</div>
</template>
<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
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
			signerToEdit: {},
			canRequestSign: loadState('libresign', 'can_request_sign', false),
		}
	},
	computed: {
		canSave() {
			return this.canRequestSign && !this.filesStore.isSigned() && this.filesStore.getFile()?.signers?.length > 0
		},
		dataSigners() {
			return this.filesStore.files[this.filesStore.selectedNodeId].signers
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
		async save() {
			const params = {
				name: this.filesStore.getFile()?.name,
				users: [],
			}
			this.dataSigners.forEach(signer => {
				const user = {
					displayName: signer.displayName,
					identify: {},
				}
				signer.identifyMethods.forEach(method => {
					if (method.method === 'account') {
						user.identify.account = method?.value?.id ?? method?.value ?? signer.uid
					} else if (method.method === 'email') {
						user.identify.email = method?.value?.id ?? method?.value ?? signer.email
					}
				})
				params.users.push(user)
			})

			try {
				if (this.filesStore.getFile().uuid) {
					params.uuid = this.filesStore.getFile().uuid
					await axios.patch(generateOcsUrl('/apps/libresign/api/v1/request-signature'), params)
				} else {
					params.file = {
						fileId: this.filesStore.selectedNodeId,
					}
					await axios.post(generateOcsUrl('/apps/libresign/api/v1/request-signature'), params)
				}
			} catch (e) {
			}
			emit('libresign:show-visible-elements')
		},
	},
}
</script>
