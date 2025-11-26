<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="user-display-password">
		<NcButton :wide="true"
			@click="$refs.uploadCertificate.triggerUpload()">
			{{ t('libresign', 'Upload certificate') }}
			<template #icon>
				<CloudUploadIcon :size="20" />
			</template>
		</NcButton>
		<NcButton v-if="signMethodsStore.hasSignatureFile()"
			:wide="true"
			@click="signMethodsStore.showModal('readCertificate')">
			{{ t('libresign', 'Read certificate') }}
			<template #icon>
				<LockOpenCheckIcon :size="20" />
			</template>
		</NcButton>
		<NcButton v-if="signMethodsStore.hasSignatureFile()"
			:wide="true"
			@click="deleteCertificate()">
			{{ t('libresign', 'Delete certificate') }}
			<template #icon>
				<DeleteIcon :size="20" />
			</template>
		</NcButton>
		<NcButton v-if="certificateEngine !== 'none' && !signMethodsStore.hasSignatureFile()"
			:wide="true"
			@click="signMethodsStore.showModal('createPassword')">
			{{ t('libresign', 'Create certificate') }}
			<template #icon>
				<CertificateIcon :size="20" />
			</template>
		</NcButton>
		<NcButton v-else-if="signMethodsStore.hasSignatureFile()"
			:wide="true"
			@click="signMethodsStore.showModal('resetPassword')">
			{{ t('librsign', 'Change password') }}
			<template #icon>
				<FileReplaceIcon :size="20" />
			</template>
		</NcButton>
		<CreatePassword v-if="mounted" />
		<ReadCertificate v-if="mounted" />
		<ResetPassword v-if="mounted" />
		<UploadCertificate ref="uploadCertificate" v-if="mounted" :use-modal="false"
			@certificate:uploaded="onCertificateUploaded" />
	</div>
</template>

<script>
import CertificateIcon from 'vue-material-design-icons/Certificate.vue'
import CloudUploadIcon from 'vue-material-design-icons/CloudUpload.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import FileReplaceIcon from 'vue-material-design-icons/FileReplace.vue'
import LockOpenCheckIcon from 'vue-material-design-icons/LockOpenCheck.vue'

import axios from '@nextcloud/axios'
import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'

import CreatePassword from '../../CreatePassword.vue'
import ReadCertificate from '../../ReadCertificate/ReadCertificate.vue'
import ResetPassword from '../../ResetPassword.vue'
import UploadCertificate from '../../UploadCertificate.vue'

import { useSignMethodsStore } from '../../../store/signMethods.js'

export default {
	name: 'ManagePassword',
	components: {
		CertificateIcon,
		CloudUploadIcon,
		DeleteIcon,
		FileReplaceIcon,
		LockOpenCheckIcon,
		NcButton,
		CreatePassword,
		ReadCertificate,
		ResetPassword,
		UploadCertificate,
	},
	setup() {
		const signMethodsStore = useSignMethodsStore()
		signMethodsStore.setHasSignatureFile(loadState('libresign', 'config', {})?.hasSignatureFile ?? false)
		return { signMethodsStore }
	},
	data() {
		return {
			modal: '',
			certificateEngine: loadState('libresign', 'certificate_engine', ''),
			mounted: false,
		}
	},
	mounted() {
		this.mounted = true
	},
	methods: {
		onCertificateUploaded() {
			this.$emit('certificate:uploaded')
		},
		async deleteCertificate() {
			await axios.delete(generateOcsUrl('/apps/libresign/api/v1/account/pfx'))
				.then(({ data }) => {
					showSuccess(data.ocs.data.message)
					this.signMethodsStore.setHasSignatureFile(false)
				})
		},
	},
}
</script>
<style lang="scss" scoped>

.user-display-password {
	display: flex;
	flex-direction: column;
	gap: 12px;
}
</style>
