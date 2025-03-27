<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="t('libresign', 'Signature background')"
		:description="t('libresign', 'Signature background')"
		class="field">
		<div class="field__row">
			<NcButton id="signature-background"
				type="secondary"
				:aria-label="t('libresign', 'Upload new background image')"
				@click="activateLocalFilePicker">
				<template #icon>
					<Upload :size="20" />
				</template>
				{{ t('libresign', 'Upload') }}
			</NcButton>
			<NcButton v-if="showReset"
				type="tertiary"
				:aria-label="t('libresign', 'Reset to default')"
				@click="undo">
				<template #icon>
					<Undo :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="showRemove"
				type="tertiary"
				:aria-label="t('libresign', 'Remove background')"
				@click="removeBackground">
				<template #icon>
					<Delete :size="20" />
				</template>
			</NcButton>
			<NcLoadingIcon v-if="showLoading"
				class="field__loading-icon"
				:size="20" />
			<input ref="input"
				:accept="acceptMime"
				type="file"
				@change="onChange">
		</div>
		<NcNoteCard v-if="errorMessage"
			type="error"
			:show-alert="true">
			<p>{{ errorMessage }}</p>
		</NcNoteCard>
		<NcNoteCard v-if="wasScalled"
			type="info"
			:show-alert="true">
			<p>{{ t('libresign', 'The signature background image was resized to fit within 350×100 pixels.') }}</p>
		</NcNoteCard>
		<div v-if="backgroundType"
			class="field__preview"
			:style="{
				'background-image': 'url(' + backgroundUrl + ')',
			}" />
	</NcSettingsSection>
</template>

<script>
import Delete from 'vue-material-design-icons/Delete.vue'
import Undo from 'vue-material-design-icons/UndoVariant.vue'
import Upload from 'vue-material-design-icons/Upload.vue'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'SignatureBackground',
	components: {
		Delete,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		NcSettingsSection,
		Undo,
		Upload,
	},
	data() {
		return {
			showLoading: false,
			wasScalled: false,
			backgroundType: 'default',
			acceptMime: ['image/png'],
			errorMessage: '',
			backgroundUrl: generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'),
		}
	},
	computed: {
		showReset() {
			return this.backgroundType === 'custom' || !this.backgroundType
		},
		showRemove() {
			if (this.backgroundType === 'custom' || this.backgroundType === 'default') {
				return true
			}
			return false
		},
	},
	async mounted() {
		await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/signature_background_type'))
			.then(({ data }) => {
				if (data.ocs.data.data) {
					this.backgroundType = data.ocs.data.data
				}
			})
	},
	methods: {
		reset() {
			this.showSuccess = false
			this.errorMessage = ''
			this.wasScalled = false
		},
		handleSuccess() {
			this.showSuccess = true
			setTimeout(() => { this.showSuccess = false }, 2000)
		},
		activateLocalFilePicker() {
			this.reset()
			// Set to null so that selecting the same file will trigger the change event
			this.$refs.input.value = null
			this.$refs.input.click()
		},
		async onChange(e) {
			const file = e.target.files[0]

			const formData = new FormData()
			formData.append('image', file)

			this.showLoading = true
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), formData)
				.then(({ data }) => {
					this.showLoading = false
					this.backgroundType = 'custom'
					this.backgroundUrl = generateOcsUrl('/apps/libresign/api/v1/admin/signature-background') + '?t=' + Date.now()
					this.wasScalled = data.ocs.data.wasScalled
					this.handleSuccess()
				})
				.catch(({ response }) => {
					this.showLoading = false
					this.errorMessage = response.data.ocs.data?.message
				})
		},
		async undo() {
			this.reset()
			this.showLoading = true
			await axios.patch(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), {
				setting: this.mimeName,
			})
				.then(() => {
					this.showLoading = false
					this.backgroundType = 'default'
					this.backgroundUrl = generateOcsUrl('/apps/libresign/api/v1/admin/signature-background') + '?t=' + Date.now()
					this.handleSuccess()
				})
				.catch(({ response }) => {
					this.showLoading = false
					this.errorMessage = response.data.ocs.data?.message
				})
		},
		async removeBackground() {
			this.reset()
			await axios.delete(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), {
				setting: this.mimeName,
				value: 'backgroundColor',
			})
				.then(() => {
					this.backgroundType = ''
					this.backgroundUrl = ''
					this.handleSuccess()
				})
				.catch(({ response }) => {
					this.errorMessage = response.data.ocs.data?.message
				})
		},
	},
}
</script>
<style lang="scss" scoped>
input[type="file"] {
	display: none;
}
.field {
	&__row {
		display: flex;
		gap: 0 4px;
	}
	&__loading-icon {
		width: 44px;
		height: 44px;
	}
	&__preview {
		width: 350px;
		height: 100px;
		background-size: initial;
		background-position: center;
		background-repeat: no-repeat;
		text-align: center;
		margin-top: 10px;
	}
}
</style>
