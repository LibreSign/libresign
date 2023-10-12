<template>
	<div class="identifySigner">
		<IdentifyAccount v-if="methods.account.enabled"
			:required="methods.account.required" />
		<IdentifyEmail v-if="methods.email.enabled"
			:required="methods.account.required" />
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
import IdentifyAccount from './IdentifyAccount.vue'
import IdentifyEmail from './IdentifyEmail.vue'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'IdentifySigner',
	components: {
		NcButton,
		NcSelect,
		IdentifyAccount,
		IdentifyEmail,
	},
	data() {
		return {
			id: null,
			methods: {
				account: {
					enabled: false,
					required: false,
				},
				email: {
					enabled: false,
					required: false,
				},
			},
		}
	},
	beforeMount() {
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
	methods: {
		saveSigner() {
			this.$emit('save-identify-signer')
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
