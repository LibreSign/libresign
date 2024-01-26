<template>
	<div v-if="listSigners" class="requestSignature">
		<NcButton v-if="canRequestSign && !signed"
			@click="addSigner">
			{{ t('libresign', 'Add signer') }}
		</NcButton>
		<Signers :signers="dataSigners"
			event="libresign:edit-signer">
			<template #actions="{signer}">
				<NcActionButton v-if="canRequestSign && !signer.sign_date"
					aria-label="Delete"
					:close-after-click="true"
					@click="deleteSigner(signer)">
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
			@click="save()">
			{{ t('libresign', 'Next') }}
		</NcButton>
		<NcButton v-if="signed"
			@click="validationFile()">
			{{ t('libresign', 'Validate') }}
		</NcButton>
		<VisibleElements :file="file" />
	</div>
	<div v-else>
		<IdentifySigner :signer-to-edit="signerToEdit"
			@cancel-identify-signer="cancelIdentifySigner"
			@save-identify-signer="signerUpdate" />
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

export default {
	name: 'RequestSignatureSidebar',
	components: {
		NcActionButton,
		NcButton,
		Delete,
		Signers,
		IdentifySigner,
		VisibleElements,
	},
	props: {
		name: {
			type: String,
			default: '',
			required: false,
		},
		file: {
			type: Object,
			default: () => {},
			required: false,
		},
		signers: {
			type: Array,
			default: () => [],
			required: false,
		},
	},
	data() {
		return {
			canRequestSign: loadState('libresign', 'can_request_sign'),
			listSigners: true,
			signerToEdit: {},
			dataSigners: [],
		}
	},
	computed: {
		canSave() {
			return this.canRequestSign && !this.signed && this.dataSigners.length > 0
		},
		signed() {
			return this.signers.filter(signer => signer.sign_date?.length > 0).length > 0
		},
	},
	watch: {
		signers(signers) {
			this.init(signers)
		},
	},
	async mounted() {
		subscribe('libresign:edit-signer', this.editSigner)
		this.init(this.signers)
	},
	beforeUnmount() {
		unsubscribe('libresign:edit-signer')
	},
	methods: {
		init(signers) {
			this.addIdentifierToAll(signers)
			this.dataSigners = signers
			this.listSigners = true
		},
		validationFile() {
			this.$router.push({ name: 'validationFile', params: { uuid: this.file.uuid } })
		},
		addIdentifierToAll(signers) {
			signers.map(signer => this.addIdentifierToSigner(signer))
		},
		addIdentifierToSigner(signer) {
			// generate unique code to new signer to be possible delete or edit
			if ((signer.identify === undefined || signer.identify === '') && signer.signRequestId === undefined) {
				signer.identify = btoa(JSON.stringify(signer))
			}
			if (signer.signRequestId) {
				signer.identify = signer.signRequestId
			}
			return signer
		},
		addSigner() {
			this.signerToEdit = {}
			this.listSigners = false
		},
		cancelIdentifySigner() {
			this.toggleAddSigner()
		},
		editSigner(signer) {
			this.signerToEdit = signer
			this.toggleAddSigner()
		},
		toggleAddSigner() {
			this.listSigners = !this.listSigners
		},
		async sendNotify(signer) {
			try {
				const body = {
					fileId: this.file.nodeId,
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
				name: this.name,
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
				if (this.file.uuid) {
					params.uuid = this.file.uuid
					await axios.patch(generateOcsUrl('/apps/libresign/api/v1/request-signature'), params)
				} else {
					params.file = {
						fileId: this.file.nodeId,
					}
					await axios.post(generateOcsUrl('/apps/libresign/api/v1/request-signature'), params)
				}
			} catch (e) {
			}
			emit('libresign:show-visible-elements')
		},
		async signerUpdate(signer) {
			this.toggleAddSigner()
			signer = this.addIdentifierToSigner(signer)
			// Remove if already exists
			for (let i = this.dataSigners.length - 1; i >= 0; i--) {
				if (this.dataSigners[i].identify === signer.identify) {
					this.dataSigners.splice(i, 1)
					break
				}
				if (this.dataSigners[i].signRequestId === signer.identify) {
					this.dataSigners.splice(i, 1)
					break
				}
			}
			this.dataSigners.push(signer)
		},
		async deleteSigner(signer) {
			if (!isNaN(this.file?.nodeId) && !isNaN(signer.signRequestId)) {
				await axios.delete(generateOcsUrl(`/apps/libresign/api/v1/sign/file_id/${this.file.nodeId}/${signer.signRequestId}`))
			}
			this.dataSigners = this.dataSigners.filter((i) => i.identify !== signer.identify)
		},
	},
}
</script>
