<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection :name="name" :description="description">
		<fieldset class="settings-section__row">
			<NcTextField :value.sync="OID"
				:label="t('libresign', 'Certificate Policy OID')"
				:placeholder="t('libresign', 'Certificate Policy OID')"
				:spellcheck="false"
				:success="dislaySuccessOID"
				@update:modelValue="saveOID" />
		</fieldset>
		<fieldset class="settings-section__row">
			<NcButton id="signature-background"
				type="secondary"
				:aria-label="t('libresign', 'Upload Certification Practice Statement (CPS) PDF')"
				:disabled="loading"
				@click="activateLocalFilePicker">
				<template #icon>
					<Upload :size="20" />
				</template>
				{{ t('libresign', 'Upload') }}
			</NcButton>
			<NcButton v-if="url"
				type="tertiary"
				:aria-label="t('libresign', 'Remove')"
				:disabled="loading"
				@click="removeCps">
				<template #icon>
					<Delete :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="url"
				type="tertiary"
				:disabled="loading"
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
	</NcSettingsSection>
</template>

<script>
import debounce from 'debounce'

import Delete from 'vue-material-design-icons/Delete.vue'
import Upload from 'vue-material-design-icons/Upload.vue'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import '@nextcloud/password-confirmation/dist/style.css'

export default {
	name: 'CertificatePolicy',
	components: {
		Delete,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		NcSettingsSection,
		NcTextField,
		Upload,
	},

	data() {
		return {
			name: t('libresign', 'Certificate policies'),
			description: t('libresign', 'Define OIDs and CPS for issued certificates.'),
			acceptMime: ['application/pdf'],
			OID: loadState('libresign', 'certificate_policies_oid'),
			url: loadState('libresign', 'certificate_policies_url'),
			loading: false,
			dislaySuccessOID: false,
			errorMessage: '',
		}
	},
	methods: {
		view() {
			if (OCA?.Viewer !== undefined) {
				OCA.Viewer.open({ path: this.url })
			} else {
				window.open(`${this.url}?_t=${Date.now()}`)
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
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/certificate-policy'), formData)
				.then(({ data }) => {
					this.url = data.ocs.data.url
					this.loading = false
				})
				.catch(({ response }) => {
					this.errorMessage = response?.data?.ocs?.data?.message
					this.loading = false
				})
		},
		async removeCps() {
			this.errorMessage = ''
			this.loading = true
			await axios.delete(generateOcsUrl('/apps/libresign/api/v1/admin/certificate-policy'))
				.then(() => {
					this.url = ''
					this.loading = false
				})
		},
		async _saveOID() {
			this.dislaySuccessOID = false
			this.errorMessage = ''
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/certificate-policy/oid'), {
				oid: this.OID,
			})
				.then(() => {
					this.dislaySuccessOID = true
					setTimeout(() => { this.dislaySuccessOID = false }, 2000)
				})
				.catch(({ response }) => {
					this.errorMessage = response?.data?.ocs?.data?.message
					this.loading = false
				})
		},
		saveOID: debounce(function () {
			this._saveOID()
		}, 500)
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
