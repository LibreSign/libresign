<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="identifySigner">
		<SignerSelect v-if="isNewSigner"
			:signer="signer"
			:placeholder="placeholder"
			:method="method"
			@update:signer="updateSigner" />
		<NcNoteCard v-else type="info">
			<template #icon>
				<NcIconSvgWrapper :size="20" :svg="getMethodIcon()" />
			</template>
			<strong>{{ identifyMethodLabel }}:</strong> {{ signer.id }}
		</NcNoteCard>

		<NcNoteCard v-if="disabled" type="warning" class="disabled-warning">
			{{ t('libresign', 'This signer cannot be used because the identification method "{method}" has been disabled by the administrator.', { method: identifyMethodLabel }) }}
		</NcNoteCard>

		<NcTextField v-if="signerSelected && !disabled"
			v-model="displayName"
			aria-describedby="name-input"
			autocapitalize="none"
			maxlength="64"
			:label="t('libresign', 'Signer name')"
			:required="true"
			:error="nameHaveError"
			:helper-text="nameHelperText"
			@update:value="onNameChange" />

		<div v-if="signerSelected && showCustomMessage && !disabled" class="description-wrapper">
			<NcCheckboxRadioSwitch v-model:checked="enableCustomMessage"
				type="switch"
				@update:checked="onToggleCustomMessage">
				{{ t('libresign', 'Add custom message') }}
			</NcCheckboxRadioSwitch>
			<NcTextArea v-if="enableCustomMessage"
				v-model="description"
				aria-describedby="description-input"
				maxlength="500"
				:label="t('libresign', 'Custom message')"
				:placeholder="t('libresign', 'Add a personal message for this signer')"
				:rows="3"
				resize="none" />
		</div>

		<div v-if="!disabled" class="identifySigner__footer">
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
import svgAccount from '@mdi/svg/svg/account.svg?raw'
import svgEmail from '@mdi/svg/svg/email.svg?raw'
import svgSms from '@mdi/svg/svg/message-processing.svg?raw'
import svgWhatsapp from '@mdi/svg/svg/whatsapp.svg?raw'
import svgXmpp from '@mdi/svg/svg/xmpp.svg?raw'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import SignerSelect from './SignerSelect.vue'

import svgSignal from '../../../img/logo-signal-app.svg?raw'
import svgTelegram from '../../../img/logo-telegram-app.svg?raw'
import { useFilesStore } from '../../store/files.js'

const iconMap = {
	svgAccount,
	svgEmail,
	svgSignal,
	svgSms,
	svgTelegram,
	svgWhatsapp,
	svgXmpp,
}

export default {
	name: 'IdentifySigner',
	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcIconSvgWrapper,
		NcNoteCard,
		NcTextArea,
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
		methods: {
			type: Array,
			default: () => [],
		},
		disabled: {
			type: Boolean,
			default: false,
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
			description: '',
			enableCustomMessage: false,
			identify: '',
			signer: {},
		}
	},
	computed: {
		signerSelected() {
			return !!this.signer?.id
		},
		isNewSigner() {
			return !this.signerToEdit || Object.keys(this.signerToEdit).length === 0
		},
		saveButtonText() {
			if (this.isNewSigner) {
				return t('libresign', 'Save')
			}
			return t('libresign', 'Update')
		},
		identifyMethodLabel() {
			if (!this.signer?.method) {
				return ''
			}
			const methodConfig = this.methods.find(m => m.name === this.signer.method)
			if (!methodConfig?.friendly_name) {
				return ''
			}
			return methodConfig.friendly_name
		},
		showCustomMessage() {
			if (this.signer?.method === 'account') {
				return this.signer?.acceptsEmailNotifications === true
			}
			return !!this.signer?.method
		},
	},
	beforeMount() {
		this.displayName = this.signerToEdit.displayName ?? ''
		this.description = this.signerToEdit.description ?? ''
		this.enableCustomMessage = !!(this.signerToEdit.description)
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
		getMethodIcon() {
			const method = this.signer?.method
			if (!method) {
				return iconMap.svgAccount
			}
			return iconMap[`svg${method.charAt(0).toUpperCase() + method.slice(1)}`] || iconMap.svgAccount
		},
		updateSigner(signer) {
			this.signer = signer ?? {}
			this.displayName = signer?.displayName ?? ''
			this.identify = signer?.id ?? ''

			if (signer?.method === 'account' && signer?.acceptsEmailNotifications === false) {
				this.enableCustomMessage = false
				this.description = ''
			}
		},
		async saveSigner() {
			if (!this.signer?.method || !this.signer?.id) {
				return
			}
			this.filesStore.signerUpdate({
				displayName: this.displayName,
				description: this.description.trim() || undefined,
				identify: this.identify,
				identifyMethods: [
					{
						method: this.signer.method,
						value: this.signer.id,
					},
				],
			})

			try {
				await this.filesStore.saveWithVisibleElements({ visibleElements: [] })
			} catch (error) {
				console.error('Error saving signer:', error)
			}

			this.displayName = ''
			this.description = ''
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
		onToggleCustomMessage(checked) {
			if (!checked) {
				this.description = ''
			}
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

	.disabled-warning {
		margin-top: 12px;
		width: 100%;
	}

	#account-or-email {
		width: 100%;
	}

	:deep(.notecard) {
		width: 100%;
		margin-bottom: 16px;

		div {
			display: flex;
			align-items: center;
			gap: 0.5em;
		}
	}
	.description-wrapper {
		width: 100%;
		margin-bottom: 16px;

		:deep(textarea) {
			margin-top: 8px;
		}
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
		padding-top: 24px;

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
