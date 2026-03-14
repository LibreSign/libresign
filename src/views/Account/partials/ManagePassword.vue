<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="user-display-password">
		<NcButton v-if="mounted"
			:wide="true"
			@click="triggerUploadCertificate">
			{{ t('libresign', 'Upload certificate') }}
			<template #icon>
				<NcIconSvgWrapper :path="mdiCloudUpload" :size="20" />
			</template>
		</NcButton>
		<NcButton v-if="signMethodsStore.hasSignatureFile()"
			:wide="true"
			@click="signMethodsStore.showModal('readCertificate')">
			{{ t('libresign', 'Read certificate') }}
			<template #icon>
				<NcIconSvgWrapper :path="mdiLockOpenCheck" :size="20" />
			</template>
		</NcButton>
		<NcButton v-if="signMethodsStore.hasSignatureFile()"
			:wide="true"
			@click="deleteCertificate()">
			{{ t('libresign', 'Delete certificate') }}
			<template #icon>
				<NcIconSvgWrapper :path="mdiDelete" :size="20" />
			</template>
		</NcButton>
		<NcButton v-if="certificateEngine !== 'none' && !signMethodsStore.hasSignatureFile()"
			:wide="true"
			@click="signMethodsStore.showModal('createPassword')">
			{{ t('libresign', 'Create certificate') }}
			<template #icon>
				<NcIconSvgWrapper :path="mdiCertificate" :size="20" />
			</template>
		</NcButton>
		<NcButton v-else-if="signMethodsStore.hasSignatureFile()"
			:wide="true"
			@click="signMethodsStore.showModal('resetPassword')">
			{{ t('librsign', 'Change password') }}
			<template #icon>
				<NcIconSvgWrapper :path="mdiFileReplace" :size="20" />
			</template>
		</NcButton>
		<CreatePassword v-if="mounted" />
		<ReadCertificate v-if="mounted" />
		<ResetPassword v-if="mounted" />
		<UploadCertificate ref="uploadCertificate" v-if="mounted" :use-modal="false"
			@certificate:uploaded="onCertificateUploaded" />
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import {
	mdiCertificate,
	mdiCloudUpload,
	mdiDelete,
	mdiFileReplace,
	mdiLockOpenCheck,
} from '@mdi/js'


import axios from '@nextcloud/axios'
import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import CreatePassword from '../../CreatePassword.vue'
import ReadCertificate from '../../ReadCertificate/ReadCertificate.vue'
import ResetPassword from '../../ResetPassword.vue'
import UploadCertificate from '../../UploadCertificate.vue'

import { useSignMethodsStore } from '../../../store/signMethods.js'
import { onMounted, ref } from 'vue'

defineOptions({
	name: 'ManagePassword',
})

const emit = defineEmits<{
	(event: 'certificate:uploaded'): void
}>()

type ManagePasswordConfig = {
	hasSignatureFile?: boolean
}

const signMethodsStore = useSignMethodsStore()
const config = loadState<ManagePasswordConfig>('libresign', 'config', {})
signMethodsStore.initializeHasSignatureFile(config.hasSignatureFile ?? false)

const certificateEngine = loadState<string>('libresign', 'certificate_engine', '')
const mounted = ref(false)
const uploadCertificate = ref<InstanceType<typeof UploadCertificate> | null>(null)

onMounted(() => {
	mounted.value = true
})

function triggerUploadCertificate() {
	uploadCertificate.value?.triggerUpload()
}

function onCertificateUploaded() {
	emit('certificate:uploaded')
}

async function deleteCertificate() {
	const { data } = await axios.delete(generateOcsUrl('/apps/libresign/api/v1/account/pfx'))
	showSuccess(data.ocs.data.message)
	signMethodsStore.setHasSignatureFile(false)
}

defineExpose({
	uploadCertificate,
	triggerUploadCertificate,
	mdiCloudUpload,
	mdiLockOpenCheck,
	mdiDelete,
	mdiCertificate,
	mdiFileReplace,
})
</script>
<style lang="scss" scoped>

.user-display-password {
	display: flex;
	flex-direction: column;
	gap: 12px;
}
</style>
