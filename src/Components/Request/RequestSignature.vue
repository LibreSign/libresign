<template>
	<div v-if="listSigners" class="requestSignature">
		<NcButton v-if="canRequestSign"
			@click="addSigner">
			{{ t('libresign', 'Add signer') }}
		</NcButton>
		<Signers :signers="dataSigners">
			<template #actions="{signer}">
				<NcActionButton v-if="canRequestSign"
					aria-label="Delete"
					:close-after-click="true"
					@click="deleteSigner(signer)">
					<template #icon>
						<Delete :size="20" />
					</template>
					{{ t('libresign', 'Delete') }}
				</NcActionButton>
				<NcActionButton v-if="!signer.signed && signer.fileUserId"
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
import { emit, subscribe } from '@nextcloud/event-bus'
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
	name: 'RequestSignature',
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
			dataFile: {},
			dataSigners: this.signers,
		}
	},
	computed: {
		canSave() {
			return this.dataSigners.length > 0
		},
	},
	watch: {
		signers(signers) {
			this.addIdentifier(signers)
			this.dataSigners = signers
			this.listSigners = true
		},
	},
	async mounted() {
		subscribe('libresign:edit-signer', this.editSigner)
	},
	methods: {
		addIdentifier(signers) {
			signers.map(signer => {
				// generate unique code to new signer to be possible delete or edit
				if ((signer.identify === undefined || signer.identify === '') && signer.fileUserId === undefined) {
					signer.identify = btoa(JSON.stringify(signer))
				}
				if (signer.fileUserId) {
					signer.identify = signer.fileUserId
				}
				return signer
			})
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
					fileId: this.dataFile.nodeId,
					fileUserId: signer.fileUserId,
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
						user.identify.account = method?.value?.id ?? signer.uid
					} else if (method.method === 'email') {
						user.identify.email = method.value ?? signer.email
					}
				})
				params.users.push(user)
			})

			try {
				if (this.dataFile.uuid) {
					params.uuid = this.dataFile.uuid
					await axios.patch(generateOcsUrl('/apps/libresign/api/v1/request-signature'), params)
				} else {
					params.file = {
						fileId: this.dataFile.nodeId,
					}
					await axios.post(generateOcsUrl('/apps/libresign/api/v1/request-signature'), params)
				}
			} catch (e) {
			}
			emit('libresign:show-visible-elements')
		},
		signerUpdate(signer) {
			this.toggleAddSigner()
			// Remove if already exists
			for (let i = this.dataSigners.length - 1; i >= 0; i--) {
				if (this.dataSigners[i].identify?.length > 0 && signer.identify?.length > 0 && this.dataSigners[i].identify === signer.identify) {
					this.dataSigners.splice(i, 1)
					break
				}
				if (this.dataSigners[i].fileUserId === signer.identify) {
					this.dataSigners.splice(i, 1)
					break
				}
			}
			this.dataSigners.push(signer)
		},
		deleteSigner(signer) {
			this.dataSigners = this.dataSigners.filter((i) => i.identify !== signer.identify)
		},
	},
}
</script>
