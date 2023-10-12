<template>
	<div class="identifySigner">
		<IdentifyAccount v-if="methods.account.enabled"
			:required="methods.account.required"
			:account="methods.account.value"
			@update:account="updateAccount" />
		<IdentifyEmail v-if="methods.email.enabled"
			:required="methods.email.required"
			:email="methods.email.value"
			@update:email="updateEmail" />
		<SignerName :name="getName()"
			@update:name="updateName" />
		<div class="identifySigner__footer">
			<div class="button-group">
				<NcButton @click="$emit('cancel-identify-signer')">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
				<NcButton type="primary" @click="saveSigner">
					{{ saveButtonText }}
				</NcButton>
			</div>
		</div>
	</div>
</template>
<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import IdentifyAccount from './IdentifyAccount.vue'
import IdentifyEmail from './IdentifyEmail.vue'
import SignerName from './SignerName.vue'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'IdentifySigner',
	components: {
		NcButton,
		NcSelect,
		NcTextField,
		IdentifyAccount,
		IdentifyEmail,
		SignerName,
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
	data() {
		return {
			id: null,
			name: '',
			identify: '',
			methods: {
				account: {
					enabled: false,
					required: false,
					value: {},
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
		if (Object.keys(this.signerToEdit).length > 0) {
			this.name = this.signerToEdit.displayName
			this.identify = this.signerToEdit.identify
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
		getName() {
			const name = this.name
			if (name) {
				return name
			}
			if (this.methods.account.enabled && this.methods.account.required && Object.keys(this.methods.account.value).length > 0) {
				return this.methods.account.value.displayName
			}
			if (this.methods.email.enabled && this.methods.email.required && this.methods.email.value.length > 0) {
				return this.methods.email.value
			}
			if (this.methods.account.enabled && Object.keys(this.methods.account.value).length > 0) {
				return this.methods.account.value.displayName
			}
			if (this.methods.email.enabled && this.methods.email.value.length > 0) {
				return this.methods.email.value
			}
		},
		saveSigner() {
			const signer = {
				displayName: this.getName(),
				identify: this.identify,
				identifyMethods: [],
			}
			let canSave = false
			if (this.methods.account.enabled) {
				if (this.methods.account.required && Object.keys(this.methods.account.value).length === 0) {
					return
				}
				if (Object.keys(this.methods.account.value).length > 0) {
					canSave = true
					signer.identifyMethods.push({
						method: 'account',
						value: this.methods.account.value,
					})
				}
			}
			if (this.methods.email.enabled) {
				if (this.methods.email.required && this.methods.email.value.length === 0) {
					return
				}
				if (this.methods.email.value?.length > 0) {
					canSave = true
					signer.identifyMethods.push({
						method: 'email',
						value: this.methods.email.value,
					})
				}
			}
			if (canSave) {
				// generate unique code to new signer to be possible delete or edit
				if (this.identify.length === 0 && this.signerToEdit.fileUserId === undefined) {
					signer.identify = btoa(JSON.stringify(signer))
				}
				if (this.signerToEdit.fileUserId) {
					signer.identify = this.signerToEdit.fileUserId
				}
				this.$emit('save-identify-signer', signer)
			}
		},
		updateEmail(email) {
			this.methods.email.value = email
		},
		updateAccount(account) {
			this.methods.account.value = account ?? {}
		},
		updateName(name) {
			this.name = name
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
