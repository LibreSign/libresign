<template>
	<NcContent class="container-account" app-name="libresign">
		<div class="content-account">
			<div class="user">
				<UserImage v-bind="{ user }" />
				<div class="details">
					<div class="user-details">
						<h3>{{ t('libresign', 'Details') }}</h3>
						<div class="user-display-name icon-user">
							<p>{{ user.displayName }}</p>
						</div>
					</div>
					<div class="user-password">
						<h3>{{ t('libresign', 'Certificate') }}</h3>
						<LockIcon :size="20" />
						<div class="user-display-password">
							<NcButton @click="uploadCertificate()"
								:wide="true">
								{{ t('libresign', 'Upload certificate') }}
								<template #icon>
									<CloudUploadIcon :size="20" />
								</template>
							</NcButton>
							<NcButton v-if="signMethodsStore.hasSignatureFile()"
								@click="deleteCertificate()"
								:wide="true">
								{{ t('libresign', 'Delete certificate') }}
								<template #icon>
									<DeleteIcon :size="20" />
								</template>
							</NcButton>
							<NcButton v-if="certificateEngine !== 'none' && !signMethodsStore.hasSignatureFile()" @click="handleModal(true)">
								{{ t('libresign', 'Create certificate') }}
							</NcButton>
							<NcButton v-else-if="signMethodsStore.hasSignatureFile()"
								@click="handleModal(true)"
								:wide="true">
								{{ t('librsign', 'Change password') }}
								<template #icon>
									<FileReplaceIcon :size="20" />
								</template>
							</NcButton>
						</div>
						<NcModal v-if="modal"
							@close="handleModal(false)">
							<CreatePassword v-if="!signMethodsStore.hasSignatureFile()" @close="handleModal(false)" />
							<ResetPassword v-if="signMethodsStore.hasSignatureFile()" @close="handleModal(false)" />
						</NcModal>
					</div>
				</div>
			</div>

			<div class="user">
				<Signatures />
				<Documents />
			</div>
		</div>
	</NcContent>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import CloudUploadIcon from 'vue-material-design-icons/CloudUpload.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import FileReplaceIcon from 'vue-material-design-icons/FileReplace.vue'
import CreatePassword from '../CreatePassword.vue'
import ResetPassword from '../ResetPassword.vue'
import UserImage from './partials/UserImage.vue'
import Signatures from './partials/Signatures.vue'
import Documents from './partials/Documents.vue'
import { useSignMethodsStore } from '../../store/signMethods.js'

export default {
	name: 'Account',

	components: {
		NcModal,
		NcContent,
		NcButton,
		LockIcon,
		CloudUploadIcon,
		DeleteIcon,
		FileReplaceIcon,
		CreatePassword,
		ResetPassword,
		Signatures,
		UserImage,
		Documents,
	},
	setup() {
		const signMethodsStore = useSignMethodsStore()
		return { signMethodsStore }
	},

	data() {
		return {
			user: getCurrentUser(),
			modal: false,
			certificateEngine: loadState('libresign', 'certificate_engine', ''),
		}
	},
	mounted() {
		this.signMethodsStore.setHasSignatureFile(loadState('libresign', 'config', {})?.hasSignatureFile ?? false)
	},
	methods: {
		uploadCertificate() {
			const input = document.createElement('input')
			// @todo PFX file, didn't worked, wrong code
			input.accept = 'application/x-pkcs12'
			input.type = 'file'

			input.onchange = async (ev) => {
				const file = ev.target.files[0]

				if (file) {
					this.doUpload(file)
				}

				input.remove()
			}

			input.click()
		},
		async doUpload(file) {
			try {
				const formData = new FormData()
				formData.append('file', file)
				const response = await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/pfx'), formData)
				showSuccess(response.data.message)
				this.signMethodsStore.setHasSignatureFile(true)
			} catch (err) {
				showError(err.response.data.message)
			}
		},
		async deleteCertificate() {
			const response = await axios.delete(generateOcsUrl('/apps/libresign/api/v1/account/pfx'))
			showSuccess(response.data.message)
			this.signMethodsStore.setHasSignatureFile(false)
		},
		handleModal(status) {
			this.modal = status
		},
	},
}
</script>

<style lang="scss">

.container-account{
	display: flex;
	flex-direction: row;

	.content-account{
		width: 100%;
		margin: 10px;
		display: flex;
		height: 100%;

		.user{
			width: 50%;
			max-width: 350px;
			display: flex;
			flex-direction: column;
			align-items: flex-start;

			&:first-child {
				width: 25%;
				min-width: 240px;
			}

			@media screen and (max-width: 768px) {
				&, &:first-child {
					width: 50%;
				}
			}

			.details{
				display: flex;
				flex-direction: column;
				width: 100%;
				padding: 10px;
				border: 0;
			}

			.user-details{
				display: flex;
				flex-direction: column;
				width: 100%;
				border: 0;

				.user-display-name[class*='icon']{
					width: 100%;
					background-position: 0px 4px;
					opacity: 0.7;
					margin-right: 10%;
					margin-bottom: 12px;
					margin-top: 12px;
					margin-left: 12px;
					padding-left: 22px;
				}
			}

			.user-password{
				display: flex;
				flex-direction: column;

				.user-display-password{
					display: flex;
					flex-direction: column;
					gap: 12px;
				}
			}
		}

		@media (max-width: 650px) {
			flex-direction: column;

			.user{
				width: 100%;
			}
		}
	}

}
</style>
