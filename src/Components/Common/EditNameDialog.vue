<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="open"
		:name="title"
		:buttons="dialogButtons"
		@closing="$emit('close')">
		<NcNoteCard v-if="localSuccessMessage" type="success">
			{{ localSuccessMessage }}
		</NcNoteCard>
		<NcNoteCard v-if="localErrorMessage" type="error">
			{{ localErrorMessage }}
		</NcNoteCard>
		<div class="edit-name-form">
			<label :for="inputId">{{ label }}</label>
			<input
				:id="inputId"
				v-model="localName"
				type="text"
				class="name-input"
				:placeholder="placeholder"
				minlength="3"
				maxlength="255"
				@keydown.enter="handleSave" />
			<span class="character-count">{{ localName.length }} / 255</span>
		</div>
	</NcDialog>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

export default {
	name: 'EditNameDialog',
	components: {
		NcButton,
		NcDialog,
		NcNoteCard,
	},
	props: {
		open: {
			type: Boolean,
			required: true,
		},
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
		loading: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['close', 'save'],
	data() {
		return {
			localName: '',
			localSuccessMessage: '',
			localErrorMessage: '',
			inputId: `edit-name-${Math.random().toString(36).substr(2, 9)}`,
		}
	},
	computed: {
		isNameValid() {
			const trimmedName = this.localName.trim()
			return trimmedName.length >= 3 && trimmedName.length <= 255
		},
		dialogButtons() {
			return [
				{
					label: this.t('libresign', 'Cancel'),
					callback: () => {
						this.$emit('close')
					},
				},
				{
					label: this.t('libresign', 'Save'),
					type: 'primary',
					disabled: !this.isNameValid || this.loading,
					callback: () => {
						this.handleSave()
					},
				},
			]
		},
	},
	watch: {
		open(newVal) {
			if (newVal) {
				this.localName = this.name || ''
				this.clearMessages()
			}
		},
		name(newVal) {
			if (this.open) {
				this.localName = newVal || ''
			}
		},
	},
	methods: {
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
		handleSave() {
			if (!this.isNameValid || this.loading) {
				return
			}

			const trimmedName = this.localName.trim()

			if (!trimmedName) {
				this.showError(this.t('libresign', 'Name cannot be empty'))
				return
			}

			if (trimmedName.length < 3) {
				this.showError(this.t('libresign', 'Name must be at least {min} characters', { min: 3 }))
				return
			}

			if (trimmedName.length > 255) {
				this.showError(this.t('libresign', 'Name must not exceed {max} characters', { max: 255 }))
				return
			}

			if (trimmedName === this.name) {
				this.$emit('close')
				return
			}

			this.$emit('save', trimmedName)
		},
	},
}
</script>

<style scoped>
.edit-name-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 8px 0;
}

.edit-name-form label {
	font-weight: 500;
	color: var(--color-text);
	font-size: 14px;
}

.name-input {
	padding: 12px 16px;
	border: 2px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
	font-size: 16px;
	width: 100%;
	min-width: 300px;
	transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.name-input:hover {
	border-color: var(--color-primary-element);
}

.name-input:focus {
	outline: none;
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 4px rgba(var(--color-primary-element-rgb), 0.1);
}

.character-count {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	text-align: right;
	margin-top: -4px;
}
</style>
