<template>
	<div class="identifySigner">
		<AccountOrEmail v-if="methods.account.enabled || methods.email.enabled"
			:required="methods.account.required || methods.email.required"
			:signer="methods.account.value || methods.email.value"
			@update:account="updateAccount"
			@update:email="updateEmail" />
		<SignerName :name="name"
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
import AccountOrEmail from './AccountOrEmail.vue'
import SignerName from './SignerName.vue'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'IdentifySigner',
	components: {
		NcButton,
		AccountOrEmail,
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
					value: {},
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
			this.identify = this.signerToEdit.identify ?? this.signerToEdit.signRequestId
			this.signerToEdit.identifyMethods.forEach(method => {
				this.updateName(method.value?.displayName ?? this.name)
				if (method.method === 'email') {
					this.methods.email.value = method.value ?? this.signerToEdit.email
				} else if (method.method === 'account') {
					this.updateName(this.signerToEdit.displayName ?? this.name)
					this.methods.account.value = method.value ?? {
						account: this.signerToEdit.uid,
						displayName: this.signerToEdit.displayName,
					}
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
				displayName: this.name,
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
				this.$emit('save-identify-signer', signer)
			}
		},
		updateEmail(email) {
			this.methods.email.value = email
		},
		updateAccount(account) {
			if (typeof account !== 'object') {
				account = {}
			} else {
				this.updateName(account.displayName ?? this.name)
			}
			this.methods.account.value = account
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
