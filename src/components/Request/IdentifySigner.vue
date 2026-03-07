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
			@update:modelValue="onNameChange" />

		<div v-if="signerSelected && showCustomMessage && !disabled" class="description-wrapper">
			<NcCheckboxRadioSwitch v-model="enableCustomMessage"
				type="switch"
				@update:model-value="onToggleCustomMessage">
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
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, onBeforeMount, ref } from 'vue'

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
import { showError } from '@nextcloud/dialogs'

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

const methodIconMap: Record<string, keyof typeof iconMap> = {
	account: 'svgAccount',
	email: 'svgEmail',
	signal: 'svgSignal',
	sms: 'svgSms',
	telegram: 'svgTelegram',
	whatsapp: 'svgWhatsapp',
	xmpp: 'svgXmpp',
}

defineOptions({
	name: 'IdentifySigner',
})

type IdentifyMethodConfig = {
	name: string
	friendly_name?: string
}

type SignerMethodValue = {
	method: string
	value: string
}

type SignerToEdit = {
	identify?: string
	signRequestId?: string
	displayName?: string
	description?: string
	identifyMethods?: SignerMethodValue[]
}

type SelectedSigner = {
	id?: string
	method?: string
	displayName?: string
	acceptsEmailNotifications?: boolean
}

type FilesStore = {
	getFile: () => { signers?: Array<Record<string, unknown>> } | null | undefined
	saveOrUpdateSignatureRequest: (payload: { signers: Array<Record<string, unknown>> }) => Promise<{ success?: boolean; message?: string }>
	disableIdentifySigner: () => void
}

const props = withDefaults(defineProps<{
	signerToEdit?: SignerToEdit
	method?: string
	placeholder?: string
	methods?: IdentifyMethodConfig[]
	disabled?: boolean
}>(), {
	signerToEdit: () => ({
		identify: '',
		displayName: '',
		identifyMethods: [],
	}),
	method: 'all',
	placeholder: t('libresign', 'Name'),
	methods: () => [],
	disabled: false,
})

const filesStore = useFilesStore() as unknown as FilesStore

const id = ref<string | null>(null)
const nameHelperText = ref('')
const nameHaveError = ref(false)
const displayName = ref('')
const description = ref('')
const enableCustomMessage = ref(false)
const identify = ref('')
const signer = ref<SelectedSigner>({})

const signerSelected = computed(() => !!signer.value?.id)
const isNewSigner = computed(() => !props.signerToEdit || Object.keys(props.signerToEdit).length === 0)
const saveButtonText = computed(() => isNewSigner.value ? t('libresign', 'Save') : t('libresign', 'Update'))
const identifyMethodLabel = computed(() => {
	if (!signer.value?.method) {
		return ''
	}
	const methodConfig = props.methods.find((item) => item.name === signer.value.method)
	if (!methodConfig?.friendly_name) {
		return ''
	}
	return methodConfig.friendly_name
})
const showCustomMessage = computed(() => {
	if (signer.value?.method === 'account') {
		return signer.value?.acceptsEmailNotifications === true
	}
	return !!signer.value?.method
})

function getMethodIcon() {
	const method = signer.value?.method
	if (!method) {
		return iconMap.svgAccount
	}
	const iconKey = methodIconMap[method] || 'svgAccount'
	return iconMap[iconKey]
}

function updateSigner(nextSigner: SelectedSigner | null) {
	signer.value = nextSigner ?? {}
	displayName.value = nextSigner?.displayName ?? ''
	identify.value = nextSigner?.id ?? ''

	if (nextSigner?.method === 'account' && nextSigner?.acceptsEmailNotifications === false) {
		enableCustomMessage.value = false
		description.value = ''
	}
}

async function saveSigner() {
	if (!signer.value?.method || !signer.value?.id) {
		return
	}
	const file = filesStore.getFile()
	const signers = Array.isArray(file?.signers) ? [...file.signers] : []
	signers.push({
		displayName: displayName.value,
		description: description.value.trim() || undefined,
		identify: identify.value,
		identifyMethods: [
			{
				method: signer.value.method,
				value: signer.value.id,
			},
		],
	})

	try {
		const response = await filesStore.saveOrUpdateSignatureRequest({ signers })
		if (response?.success === false) {
			showError(response.message ?? t('libresign', 'Failed to save or update signature request'))
			return
		}
	} catch {
		showError(t('libresign', 'Failed to save or update signature request'))
		return
	}

	displayName.value = ''
	description.value = ''
	identify.value = ''
	signer.value = {}
	filesStore.disableIdentifySigner()
}

function onNameChange() {
	const name = displayName.value.trim()
	if (name.length > 2) {
		nameHelperText.value = ''
		nameHaveError.value = false
		return
	}
	nameHelperText.value = t('libresign', 'Please enter signer name.')
	nameHaveError.value = true
}

function onToggleCustomMessage(checked: boolean) {
	if (!checked) {
		description.value = ''
	}
}

onBeforeMount(() => {
	if (!props.signerToEdit) {
		return
	}
	displayName.value = props.signerToEdit.displayName ?? ''
	description.value = props.signerToEdit.description ?? ''
	enableCustomMessage.value = !!props.signerToEdit.description
	identify.value = props.signerToEdit.identify ?? props.signerToEdit.signRequestId ?? ''
	if (Object.keys(props.signerToEdit).length > 0 && props.signerToEdit.identifyMethods?.length) {
		const method = props.signerToEdit.identifyMethods[0]
		signer.value = {
			id: method.value,
			method: method.method,
			displayName: props.signerToEdit.displayName,
		}
	}
})

defineExpose({
	props,
	t,
	filesStore,
	id,
	nameHelperText,
	nameHaveError,
	displayName,
	description,
	enableCustomMessage,
	identify,
	signer,
	signerSelected,
	isNewSigner,
	saveButtonText,
	identifyMethodLabel,
	showCustomMessage,
	getMethodIcon,
	updateSigner,
	saveSigner,
	onNameChange,
	onToggleCustomMessage,
})
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
