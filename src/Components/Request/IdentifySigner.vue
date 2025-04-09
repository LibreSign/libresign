<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="identifySigner">
		<AccountOrEmail v-if="methods.account.enabled || methods.email.enabled"
			:required="methods.account.required || methods.email.required"
			:signer="signer"
			:placeholder="placeholder"
			@update:account="updateAccount"
			@update:email="updateEmail"
			@update:display-name="updateDisplayName" />
		<label v-if="signerSelected" for="name-input">{{ t('libresign', 'Signer name') }}</label>
		<NcTextField v-if="signerSelected"
			v-model="signerSelected"
			aria-describedby="name-input"
			autocapitalize="none"
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
import { loadState } from '@nextcloud/initial-state'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import AccountOrEmail from './AccountOrEmail.vue'

import { useFilesStore } from '../../store/files.js'

export default {
	name: 'IdentifySigner',
	components: {
		NcButton,
		NcTextField,
		AccountOrEmail,
	},
	props: {
		signerToEdit: {
			type: Object,
			default: () => ({
				identify: '',
				displayName: '',
				identifyMethods: [],
			}),
			required: false,
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
			methods: {
				account: {
					enabled: false,
					required: false,
					value: '',
				},
				email: {
					enabled: false,
					required: false,
					value: '',
				},
			},
		}
	},
	computed: {
		signer() {
			if (this.methods.account.value.length > 0) {
				return {
					id: this.methods.account.value,
					icon: 'icon-user',
					isNoUser: false,
					displayName: this.signerToEdit.displayName,
				}
			} else if (this.methods.email.value.length > 0) {
				return {
					id: this.methods.email.value,
					icon: 'icon-mail',
					isNoUser: true,
					displayName: this.signerToEdit.displayName,
				}
			} else {
				return {}
			}
		},
		signerSelected() {
			return this.methods.account.value || this.methods.email.value
		},
		isNewSigner() {
			return this.id === null || this.id === undefined
		},
		placeholder() {
			if (!this.methods.account.enabled && this.methods.email.enabled) {
				return t('libresign', 'Email')
			}
			return t('libresign', 'Name')
		},
		saveButtonText() {
			if (this.isNewSigner) {
				return t('libresign', 'Save')
			}
			return t('libresign', 'Update')
		},
	},
	beforeMount() {
		this.displayName = ''
		this.identify = ''
		this.methods.account.value = ''
		this.methods.email.value = ''
		if (Object.keys(this.signerToEdit).length > 0) {
			this.identify = this.signerToEdit.identify ?? this.signerToEdit.signRequestId
			this.updateDisplayName(this.signerToEdit.displayName)
			this.signerToEdit.identifyMethods.forEach(method => {
				if (method.method === 'email') {
					this.methods.email.value = method.value
				} else if (method.method === 'account') {
					this.methods.account.value = method.value
				}
			})
		}
		const methods = loadState('libresign', 'identify_methods')
		methods.forEach((method) => {
			if (method.name === 'account') {
				this.methods.account.enabled = method.enabled
				this.methods.account.required = method.mandatory
			} else if (method.name === 'email') {
				this.methods.email.enabled = method.enabled
				this.methods.email.required = method.mandatory
			}
		})
	},
	methods: {
		saveSigner() {
			const signer = {
				displayName: this.displayName,
				identify: this.identify,
				identifyMethods: [],
			}
			let canSave = false
			if (this.methods.account.enabled) {
				if (Object.keys(this.methods.account.value).length > 0) {
					canSave = true
					signer.identifyMethods.push({
						method: 'account',
						value: this.methods.account.value,
					})
				}
			}
			if (this.methods.email.enabled) {
				if (Object.keys(this.methods.email.value).length > 0) {
					canSave = true
					signer.identifyMethods.push({
						method: 'email',
						value: this.methods.email.value,
					})
				}
			}
			if (canSave) {
				this.filesStore.signerUpdate(signer)
			}
			this.displayName = ''
			this.identify = ''
			this.identifyMethods = []
			this.filesStore.disableIdentifySigner()
		},
		updateDisplayName(name) {
			this.displayName = name ?? ''
		},
		updateEmail(email) {
			if (typeof email !== 'object') {
				this.methods.email.value = ''
			} else {
				this.methods.email.value = email.id
			}
		},
		updateAccount(account) {
			if (typeof account !== 'object') {
				this.methods.account.value = ''
			} else {
				this.methods.account.value = account.id
			}
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
