<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="identifySigner">
		<SignerSelect :signer="signer"
			:placeholder="placeholder"
			:method="method"
			@update:signer="updateSigner" />

		<label v-if="signerSelected" for="name-input">{{ t('libresign', 'Signer name') }}</label>
		<NcTextField v-if="signerSelected"
			v-model="displayName"
			aria-describedby="name-input"
			autocapitalize="none"
			maxlength="64"
			:label="t('libresign', 'Signer name')"
			:required="true"
			:error="nameHaveError"
			:helper-text="nameHelperText"
			@update:value="onNameChange" />
		<div class="identifySigner__footer">
			<div class="button-group">
				<NcButton @click="filesStore.disableIdentifySigner()">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
				<NcButton variant="primary"
					:disabled="!signerSelected"
					@click="saveSigner">
					{{ saveButtonText }}
				</NcButton>
			</div>
		</div>
	</div>
</template>
<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import SignerSelect from './SignerSelect.vue'

import { useFilesStore } from '../../store/files.js'

export default {
	name: 'IdentifySigner',
	components: {
		NcButton,
		NcTextField,
		SignerSelect,
	},
	props: {
		signerToEdit: {
			type: Object,
			default: () => ({
				identify: '',
				displayName: '',
				identifyMethods: [],
			}),
		},
		method: {
			type: String,
			default: 'all',
		},
		placeholder: {
			type: String,
			default: t('libresign', 'Name'),
		},
	},
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
	data() {
		return {
			id: null,
			nameHelperText: '',
			nameHaveError: false,
			displayName: '',
			identify: '',
			signer: {},
		}
	},
	computed: {
		signerSelected() {
			return !!this.signer?.id
		},
		isNewSigner() {
			return this.id === null || this.id === undefined
		},
		saveButtonText() {
			if (this.isNewSigner) {
				return t('libresign', 'Save')
			}
			return t('libresign', 'Update')
		},
	},
	beforeMount() {
		this.displayName = this.signerToEdit.displayName ?? ''
		this.identify = this.signerToEdit.identify ?? this.signerToEdit.signRequestId ?? ''
		if (Object.keys(this.signerToEdit).length > 0 && this.signerToEdit.identifyMethods?.length) {
			const method = this.signerToEdit.identifyMethods[0]
			this.signer = {
				id: method.value,
				method: method.method,
				displayName: this.signerToEdit.displayName,
			}
		}
	},
	methods: {
		updateSigner(signer) {
			this.signer = signer
			this.displayName = signer.displayName ?? ''
			this.identify = signer.id ?? ''
		},
		saveSigner() {
			if (!this.signer?.method || !this.signer?.id) {
				return
			}
			this.filesStore.signerUpdate({
				displayName: this.displayName,
				identify: this.identify,
				identifyMethods: [
					{
						method: this.signer.method,
						value: this.signer.id,
					},
				],
			})
			this.displayName = ''
			this.identify = ''
			this.signer = {}
			this.filesStore.disableIdentifySigner()
		},
		onNameChange() {
			const name = this.displayName.trim()
			if (name.length > 2) {
				this.nameHelperText = ''
				this.nameHaveError = false
				return
			}
			this.nameHelperText = t('libresign', 'Please enter signer name.')
			this.nameHaveError = true
		},
	},
}
</script>

<style lang="scss" scoped>
.identifySigner {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	width: 96%;
	margin: 0 auto;

	#account-or-email {
		width: 100%;
	}

	&__footer {
		width: 100%;
		display: flex;
		position: sticky;
		bottom: 0;
		flex-direction: column;
		justify-content: space-between;
		align-items: flex-start;
		background: linear-gradient(to bottom, rgba(255, 255, 255, 0), var(--color-main-background));

		.button-group {
			display: flex;
			justify-content: space-between;
			width: 100%;
			margin-top: 16px;

			button {
				margin-left: 16px;

				&:first-child {
					margin-left: 0;
				}
			}
		}
	}
}
</style>
