<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<fieldset class="settings-section__row">
			<NcTextField :value.sync="OID"
				:label="t('libresign', 'Certificate Policy OID')"
				:placeholder="t('libresign', 'Certificate Policy OID')"
				:spellcheck="false"
				:success="dislaySuccessOID"
				:disabled="loading || disabled"
				:error="!OID"
				@update:modelValue="saveOID" />
		</fieldset>
		<fieldset class="settings-section__row">
			<NcButton id="signature-background"
				:variant="CPS ? 'secondary' : 'error'"
				:aria-label="t('libresign', 'Upload Certification Practice Statement (CPS) PDF')"
				:disabled="loading || disabled"
				@click="activateLocalFilePicker">
				<template #icon>
					<Upload :size="20" />
				</template>
				{{ t('libresign', 'Upload Certification Practice Statement (CPS) PDF') }}
			</NcButton>
			<NcButton v-if="CPS"
				variant="tertiary"
				:aria-label="t('libresign', 'Remove')"
				:disabled="loading || disabled"
				@click="removeCps">
				<template #icon>
					<Delete :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="CPS"
				variant="tertiary"
				:disabled="loading || disabled"
				@click="view">
				{{ t('libresign', 'View') }}
			</NcButton>
			<NcLoadingIcon v-if="loading"
				class="settings-section__loading-icon"
				:size="20" />
			<input ref="input"
				:accept="acceptMime"
				type="file"
				@change="onUploadCsp">
		</fieldset>
		<div class="settings-section__row">
			<NcNoteCard v-if="errorMessage"
				type="error"
				:show-alert="true">
				<p>{{ errorMessage }}</p>
			</NcNoteCard>
		</div>
	</div>
</template>

<script>
import debounce from 'debounce'

import Delete from 'vue-material-design-icons/Delete.vue'
import Upload from 'vue-material-design-icons/Upload.vue'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import '@nextcloud/password-confirmation/dist/style.css'

export default {
	name: 'CertificatePolicy',
	components: {
		Delete,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		NcTextField,
		Upload,
	},
	props: {
		disabled: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			acceptMime: ['application/pdf'],
			OID: loadState('libresign', 'certificate_policies_oid'),
			CPS: loadState('libresign', 'certificate_policies_cps'),
			loading: false,
			dislaySuccessOID: false,
			errorMessage: '',
		}
	},
	computed: {
		certificatePolicyValid() {
			return this.OID && this.CPS
		},
	},
	mounted() {
		this.$emit('certificate-policy-valid', this.certificatePolicyValid)
	},
	methods: {
		view() {
			if (OCA?.Viewer !== undefined) {
				OCA.Viewer.open({ path: this.CPS })
			} else {
				window.open(`${this.CPS}?_t=${Date.now()}`)
			}
		},
		activateLocalFilePicker() {
			// Set to null so that selecting the same file will trigger the change event
			this.$refs.input.value = null
			this.$refs.input.click()
		},
		async onUploadCsp(e) {
			const file = e.target.files[0]

			const formData = new FormData()
			formData.append('pdf', file)

			this.errorMessage = ''
			this.loading = true
			this.$emit('certificate-policy-valid', this.certificatePolicyValid)
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/certificate-policy'), formData)
				.then(({ data }) => {
					this.CPS = data.ocs.data.CPS
					this.$emit('certificate-policy-valid', this.certificatePolicyValid)
					this.loading = false
				})
				.catch(({ response }) => {
					this.errorMessage = response?.data?.ocs?.data?.message
					this.$emit('certificate-policy-valid', this.certificatePolicyValid)
					this.loading = false
				})
		},
		async removeCps() {
			this.errorMessage = ''
			this.loading = true
			this.$emit('certificate-policy-valid', this.certificatePolicyValid)
			await axios.delete(generateOcsUrl('/apps/libresign/api/v1/admin/certificate-policy'))
				.then(() => {
					this.CPS = ''
					this.$emit('certificate-policy-valid', this.certificatePolicyValid)
					this.loading = false
				})
		},
		async _saveOID() {
			this.dislaySuccessOID = false
			this.errorMessage = ''
			this.$emit('certificate-policy-valid', this.certificatePolicyValid)
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/certificate-policy/oid'), {
				oid: this.OID,
			})
				.then(() => {
					this.dislaySuccessOID = true
					this.$emit('certificate-policy-valid', this.certificatePolicyValid)
					setTimeout(() => { this.dislaySuccessOID = false }, 2000)
				})
				.catch(({ response }) => {
					this.errorMessage = response?.data?.ocs?.data?.message
					this.$emit('certificate-policy-valid', this.certificatePolicyValid)
					this.loading = false
				})
		},
		saveOID: debounce(function() {
			this._saveOID()
		}, 500),
	},

}
</script>

<style lang="scss" scoped>
.settings-section{
	display: flex;
	flex-direction: column;
	&:deep(.settings-section__name) {
		justify-content: unset;
	}
	&__row {
		display: flex;
		gap: 0 4px;
	}
	input[type="file"] {
		display: none;
	}
}
</style>
