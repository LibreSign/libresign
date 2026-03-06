<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="t('libresign', 'Upload certificate')"
		@closing="closeDialog">
		<NcNoteCard v-for="(error, index) in displayErrors"
			:key="index"
			:heading="error.title || ''"
			type="error">
			{{ error.message }}
		</NcNoteCard>
		<template #actions>
			<NcButton @click="closeDialog">
				{{ t('libresign', 'Close') }}
			</NcButton>
			<NcButton variant="primary"
				@click="triggerUpload">
				{{ t('libresign', 'Upload certificate') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import { useSignMethodsStore } from '../store/signMethods.js'

defineOptions({
	name: 'UploadCertificate',
})

type UploadError = {
	title?: string
	message: string
}

type SignMethodsStore = {
	modal: {
		uploadCertificate: boolean
	}
	closeModal: (modalName: string) => void
	setHasSignatureFile: (value: boolean) => void
}

const props = withDefaults(defineProps<{
	useModal?: boolean
	errors?: UploadError[]
}>(), {
	useModal: true,
	errors: () => [],
})

const emit = defineEmits<{
	'certificate:uploaded': []
}>()

const signMethodsStore = useSignMethodsStore() as SignMethodsStore
const localErrors = ref<UploadError[]>([])

const showModal = computed(() => props.useModal && signMethodsStore.modal.uploadCertificate)
const displayErrors = computed(() => [...props.errors, ...localErrors.value])

function closeDialog() {
	localErrors.value = []
	if (props.useModal) {
		signMethodsStore.closeModal('uploadCertificate')
	}
}

function triggerUpload() {
	const input = document.createElement('input')
	input.accept = '.pfx'
	input.type = 'file'

	input.onchange = async (event) => {
		const target = event.target as HTMLInputElement | null
		const file = target?.files?.[0]

		if (file) {
			await doUpload(file)
		} else if (props.useModal) {
			signMethodsStore.closeModal('uploadCertificate')
		}

		input.remove()
	}

	input.oncancel = () => {
		if (props.useModal) {
			signMethodsStore.closeModal('uploadCertificate')
		}
		input.remove()
	}

	input.click()
}

async function doUpload(file: File) {
	const formData = new FormData()
	formData.append('file', file)

	try {
		const { data } = await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/pfx'), formData)
		showSuccess(data.ocs.data.message)
		signMethodsStore.setHasSignatureFile(true)
		localErrors.value = []
		if (props.useModal) {
			signMethodsStore.closeModal('uploadCertificate')
		}
		emit('certificate:uploaded')
	} catch (error) {
		const response = (error as { response?: { data?: { ocs?: { data?: { message?: string, errors?: UploadError[] } } } } }).response
		if (response?.data?.ocs?.data?.message) {
			showError(response.data.ocs.data.message)
		}
		localErrors.value = response?.data?.ocs?.data?.errors ?? []
	}
}

defineExpose({
	signMethodsStore,
	localErrors,
	showModal,
	displayErrors,
	closeDialog,
	triggerUpload,
	doUpload,
})
</script>
