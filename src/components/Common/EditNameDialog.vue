<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog
		:name="title"
		:buttons="dialogButtons">
		<NcNoteCard v-if="localSuccessMessage" type="success">
			{{ localSuccessMessage }}
		</NcNoteCard>
		<NcNoteCard v-if="localErrorMessage" type="error">
			{{ localErrorMessage }}
		</NcNoteCard>
		<NcTextField v-model="localName"
			:label="label"
			:placeholder="placeholder"
			:minlength="ENVELOPE_NAME_MIN_LENGTH"
			:maxlength="ENVELOPE_NAME_MAX_LENGTH"
			:helper-text="`${localName.length} / ${ENVELOPE_NAME_MAX_LENGTH}`"
			@keydown.enter="handleSave" />
	</NcDialog>
</template>

<script>
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { ENVELOPE_NAME_MIN_LENGTH, ENVELOPE_NAME_MAX_LENGTH } from '../../constants.js'

export default {
	name: 'EditNameDialog',
	components: {
		NcButton,
		NcDialog,
		NcNoteCard,
		NcTextField,
	},
	props: {
		name: {
			type: String,
			default: '',
		},
		title: {
			type: String,
			default: 'Edit name',
		},
		label: {
			type: String,
			default: 'Name',
		},
		placeholder: {
			type: String,
			default: 'Enter name',
		},
	},
	emits: ['close'],
	data() {
		return {
			localName: this.name || '',
			localSuccessMessage: '',
			localErrorMessage: '',
			ENVELOPE_NAME_MIN_LENGTH,
			ENVELOPE_NAME_MAX_LENGTH,
		}
	},
	computed: {
		isNameValid() {
			const trimmedName = this.localName.trim()
			return trimmedName.length >= ENVELOPE_NAME_MIN_LENGTH && trimmedName.length <= ENVELOPE_NAME_MAX_LENGTH
		},
		dialogButtons() {
			return [
				{
					label: this.t('libresign', 'Cancel'),
					callback: () => {
						this.handleClose()
					},
				},
				{
					label: this.t('libresign', 'Save'),
					type: 'primary',
					disabled: !this.isNameValid,
					callback: () => {
						this.handleSave()
					},
				},
			]
		},
	},
	watch: {
		name(newVal) {
			this.localName = newVal || ''
		},
	},
	methods: {
		t,
		clearMessages() {
			this.localSuccessMessage = ''
			this.localErrorMessage = ''
		},
		showSuccess(message) {
			this.clearMessages()
			this.localSuccessMessage = message
			setTimeout(() => {
				this.localSuccessMessage = ''
			}, 5000)
		},
		showError(message) {
			this.clearMessages()
			this.localErrorMessage = message
		},
		handleClose() {
			this.$emit('close', null)
		},
		handleSave() {
			if (!this.isNameValid) {
				return
			}

			const trimmedName = this.localName.trim()

			if (!trimmedName) {
				this.showError(this.t('libresign', 'Name cannot be empty'))
				return
			}

			if (trimmedName.length < ENVELOPE_NAME_MIN_LENGTH) {
				this.showError(this.t('libresign', 'Name must be at least {min} characters', { min: ENVELOPE_NAME_MIN_LENGTH }))
				return
			}

			if (trimmedName.length > ENVELOPE_NAME_MAX_LENGTH) {
				this.showError(this.t('libresign', 'Name must not exceed {max} characters', { max: ENVELOPE_NAME_MAX_LENGTH }))
				return
			}

			this.$emit('close', trimmedName)
		},
	},
}
</script>

<style scoped>
/* NcTextField handles its own styling */
</style>
