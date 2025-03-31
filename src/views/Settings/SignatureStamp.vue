<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name" :description="description">
		<div class="settings-section__row">
			<NcButton id="signature-background"
				type="secondary"
				:aria-label="t('libresign', 'Upload new background image')"
				@click="activateLocalFilePicker">
				<template #icon>
					<Upload :size="20" />
				</template>
				{{ t('libresign', 'Upload') }}
			</NcButton>
			<NcButton v-if="showResetBackground"
				type="tertiary"
				:aria-label="t('libresign', 'Reset to default')"
				@click="undoBackground">
				<template #icon>
					<Undo :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="showRemoveBackground"
				type="tertiary"
				:aria-label="t('libresign', 'Remove background')"
				@click="removeBackground">
				<template #icon>
					<Delete :size="20" />
				</template>
			</NcButton>
			<NcLoadingIcon v-if="showLoadingBackground"
				class="settings-section__loading-icon"
				:size="20" />
			<input ref="input"
				:accept="acceptMime"
				type="file"
				@change="onChangeBackground">
		</div>
		<div class="settings-section__row">
			<NcNoteCard v-if="errorMessageBackground"
				type="error"
				:show-alert="true">
				<p>{{ errorMessageBackground }}</p>
			</NcNoteCard>
			<NcNoteCard v-if="wasScalled"
				type="info"
				:show-alert="true">
				<p>{{ t('libresign', 'The signature background image was resized to fit within 350×100 pixels.') }}</p>
			</NcNoteCard>
		</div>
		<div class="settings-section__row">
			<div v-if="backgroundType !== 'deleted'"
				class="settings-section__preview"
				:style="{
					'background-image': 'url(' + backgroundUrl + ')',
				}" />
		</div>
	</NcSettingsSection>
</template>
<script>
import Delete from 'vue-material-design-icons/Delete.vue'
import Undo from 'vue-material-design-icons/UndoVariant.vue'
import Upload from 'vue-material-design-icons/Upload.vue'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'


export default {
	name: 'SignatureStamp',
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
			name: t('libresign', 'Signature stamp'),
			description: t('libresign', 'The signature stamp is the element '),
			showLoadingBackground: false,
			wasScalled: false,
			backgroundType: loadState('libresign', 'signature_background_type'),
			acceptMime: ['image/png'],
			errorMessageBackground: '',
			backgroundUrl: generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'),
		}
	},
	computed: {
		showResetBackground() {
			return this.backgroundType === 'deleted' || !this.backgroundType
		},
		showRemoveBackground() {
			if (this.backgroundType === 'custom' || this.backgroundType === 'default') {
				return true
			}
			return false
		},
	},
	methods: {
		reset() {
			this.showSuccess = false
			this.errorMessageBackground = ''
			this.wasScalled = false
		},
		handleSuccessBackground() {
			this.showSuccess = true
			setTimeout(() => { this.showSuccess = false }, 2000)
		},
		activateLocalFilePicker() {
			this.reset()
			// Set to null so that selecting the same file will trigger the change event
			this.$refs.input.value = null
			this.$refs.input.click()
		},
		async onChangeBackground(e) {
			const file = e.target.files[0]

			const formData = new FormData()
			formData.append('image', file)

			this.showLoadingBackground = true
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), formData)
				.then(({ data }) => {
					this.showLoadingBackground = false
					this.backgroundType = 'custom'
					this.backgroundUrl = generateOcsUrl('/apps/libresign/api/v1/admin/signature-background') + '?t=' + Date.now()
					this.wasScalled = data.ocs.data.wasScalled
					this.handleSuccessBackground()
				})
				.catch(({ response }) => {
					this.showLoadingBackground = false
					this.errorMessageBackground = response.data.ocs.data?.message
				})
		},
		async undoBackground() {
			this.reset()
			this.showLoadingBackground = true
			await axios.patch(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), {
				setting: this.mimeName,
			})
				.then(() => {
					this.showLoadingBackground = false
					this.backgroundType = 'default'
					this.backgroundUrl = generateOcsUrl('/apps/libresign/api/v1/admin/signature-background') + '?t=' + Date.now()
					this.handleSuccessBackground()
				})
				.catch(({ response }) => {
					this.showLoadingBackground = false
					this.errorMessageBackground = response.data.ocs.data?.message
				})
		},
		async removeBackground() {
			this.reset()
			await axios.delete(generateOcsUrl('/apps/libresign/api/v1/admin/signature-background'), {
				setting: this.mimeName,
				value: 'backgroundColor',
			})
				.then(() => {
					this.backgroundType = 'deleted'
					this.backgroundUrl = ''
					this.handleSuccessBackground()
				})
				.catch(({ response }) => {
					this.errorMessageBackground = response.data.ocs.data?.message
				})
		},
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
		border: var(--border-width-input, 2px) solid var(--color-border-maxcontrast);
	}
	input[type="file"] {
		display: none;
	}
}
</style>
