<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<fieldset class="settings-section__row">
			<NcTextField v-model="OID"
				:label="t('libresign', 'Certificate Policy OID')"
				:placeholder="t('libresign', 'Certificate Policy OID')"
				:spellcheck="false"
				:success="dislaySuccessOID"
				:disabled="loading || disabled"
				:error="!OID && !CPS"
				@update:modelValue="saveOID" />
		</fieldset>
		<fieldset class="settings-section__row">
			<NcButton id="signature-background"
				:variant="CPS ? 'secondary' : 'error'"
				:aria-label="t('libresign', 'Upload Certification Practice Statement (CPS) PDF')"
				:disabled="loading || disabled"
				@click="activateLocalFilePicker">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUpload" :size="20" />
				</template>
				{{ t('libresign', 'Upload Certification Practice Statement (CPS) PDF') }}
			</NcButton>
			<NcButton v-if="CPS"
				variant="tertiary"
				:aria-label="t('libresign', 'Remove')"
				:disabled="loading || disabled"
				@click="removeCps">
				<template #icon>
					<NcIconSvgWrapper :path="mdiDelete" :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="CPS"
				variant="tertiary"
				:disabled="loading || disabled"
				@click="view">
				{{ t('libresign', 'View') }}
			</NcButton>
			<NcLoadingIcon v-if="loading"
				class="settings-section__loading-icon"
				:size="20" />
			<input ref="input"
				:accept="acceptMime"
				type="file"
				@change="onUploadCsp">
		</fieldset>
		<div class="settings-section__row">
			<NcNoteCard v-if="errorMessage"
				type="error"
				:show-alert="true">
				<p>{{ errorMessage }}</p>
			</NcNoteCard>
		</div>
	</div>
</template>

<script setup lang="ts">
import debounce from 'debounce'
import {
	mdiDelete,
	mdiUpload,
} from '@mdi/js'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, onMounted, ref, useTemplateRef } from 'vue'

import { openDocument } from '../../utils/viewer.js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import '@nextcloud/password-confirmation/style.css'

defineOptions({
	name: 'CertificatePolicy',
})

const props = withDefaults(defineProps<{
	disabled?: boolean
}>(), {
	disabled: false,
})

const emit = defineEmits<{
	(event: 'certificate-policy-valid', valid: string): void
}>()

type CertificatePolicyUploadResponse = {
	ocs: {
		data: {
			CPS: string
		}
	}
}

type CertificatePolicySettingsResponse = {
	ocs: {
		data: {
			message?: string
		}
	}
}

const input = useTemplateRef<HTMLInputElement>('input')
const acceptMime = ['application/pdf']
const OID = ref(loadState('libresign', 'certificate_policies_oid'))
const CPS = ref(loadState('libresign', 'certificate_policies_cps'))
const loading = ref(false)
const dislaySuccessOID = ref(false)
const errorMessage = ref('')

const certificatePolicyValid = computed(() => CPS.value)

function emitValidity() {
	emit('certificate-policy-valid', certificatePolicyValid.value)
}

function view() {
	openDocument({
		fileUrl: CPS.value,
		filename: 'Certificate Policy',
		nodeId: null,
	})
}

function activateLocalFilePicker() {
	if (!input.value) {
		return
	}

	input.value.value = ''
	input.value.click()
}

async function onUploadCsp(event: Event) {
	const target = event.target as HTMLInputElement
	const file = target.files?.[0]

	if (!file) {
		return
	}

	const formData = new FormData()
	formData.append('pdf', file)

	errorMessage.value = ''
	loading.value = true
	emitValidity()

	await axios.post<CertificatePolicyUploadResponse>(generateOcsUrl('/apps/libresign/api/v1/admin/certificate-policy'), formData)
		.then(({ data }) => {
			CPS.value = data.ocs.data.CPS
			emitValidity()
		})
		.catch(({ response }: { response?: { data?: CertificatePolicySettingsResponse } }) => {
			errorMessage.value = response?.data?.ocs?.data?.message ?? ''
			emitValidity()
		})
		.finally(() => {
			loading.value = false
		})
}

async function removeCps() {
	errorMessage.value = ''
	loading.value = true
	emitValidity()

	await axios.delete(generateOcsUrl('/apps/libresign/api/v1/admin/certificate-policy'))
		.then(() => {
			CPS.value = ''
			emitValidity()
		})
		.finally(() => {
			loading.value = false
		})
}

async function _saveOID() {
	dislaySuccessOID.value = false
	errorMessage.value = ''
	emitValidity()

	await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/certificate-policy/oid'), {
		oid: OID.value,
	})
		.then(() => {
			dislaySuccessOID.value = true
			emitValidity()
			setTimeout(() => {
				dislaySuccessOID.value = false
			}, 2000)
		})
		.catch(({ response }: { response?: { data?: CertificatePolicySettingsResponse } }) => {
			errorMessage.value = response?.data?.ocs?.data?.message ?? ''
			emitValidity()
			loading.value = false
		})
}

const saveOID = debounce(() => {
	void _saveOID()
}, 500)

onMounted(() => {
	emitValidity()
})

defineExpose({
	acceptMime,
	OID,
	CPS,
	loading,
	dislaySuccessOID,
	errorMessage,
	certificatePolicyValid,
	view,
	activateLocalFilePicker,
	onUploadCsp,
	removeCps,
	_saveOID,
	saveOID,
	props,
})
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
	input[type="file"] {
		display: none;
	}
}
</style>
