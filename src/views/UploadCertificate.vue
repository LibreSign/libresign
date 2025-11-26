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

<script>
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import { useSignMethodsStore } from '../store/signMethods.js'

export default {
	name: 'UploadCertificate',
	components: {
		NcButton,
		NcDialog,
		NcNoteCard,
	},
	props: {
		useModal: {
			type: Boolean,
			default: true,
		},
		errors: {
			type: Array,
			default: () => [],
		},
	},
	setup() {
		const signMethodsStore = useSignMethodsStore()
		return { signMethodsStore }
	},
	data() {
		return {
			localErrors: [],
		}
	},
	computed: {
		showModal() {
			return this.useModal && this.signMethodsStore.modal.uploadCertificate
		},
		displayErrors() {
			return [...this.errors, ...this.localErrors]
		},
	},
	methods: {
		closeDialog() {
			this.localErrors = []
			if (this.useModal) {
				this.signMethodsStore.closeModal('uploadCertificate')
			}
		},
		triggerUpload() {
			const input = document.createElement('input')
			input.accept = '.pfx'
			input.type = 'file'

			input.onchange = async (ev) => {
				const file = ev.target.files[0]

				if (file) {
					this.doUpload(file)
				} else if (this.useModal) {
					this.signMethodsStore.closeModal('uploadCertificate')
				}

				input.remove()
			}

			input.oncancel = () => {
				if (this.useModal) {
					this.signMethodsStore.closeModal('uploadCertificate')
				}
				input.remove()
			}

			input.click()
		},
		async doUpload(file) {
			const formData = new FormData()
			formData.append('file', file)
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/pfx'), formData)
				.then(({ data }) => {
					showSuccess(data.ocs.data.message)
					this.signMethodsStore.setHasSignatureFile(true)
					this.localErrors = []
					if (this.useModal) {
						this.signMethodsStore.closeModal('uploadCertificate')
					}
					this.$emit('certificate:uploaded')
				})
				.catch(({ response }) => {
					if (response?.data?.ocs?.data?.message) {
						showError(response.data.ocs.data.message)
					}
					if (response?.data?.ocs?.data?.errors) {
						this.errors = response.data.ocs.data.errors
					}
				})
		},
	},
}
</script>
