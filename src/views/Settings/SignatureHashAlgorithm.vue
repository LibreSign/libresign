<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		:name="t('libresign', 'Signature hash algorithm')"
		:description="t('libresign', 'Hash algorithm used for signature.')">
		<NcSelect :key="idKey"
			v-model="selected"
			label="displayname"
			:no-wrap="false"
			:aria-label-combobox="t('libresign', 'Hash algorithm used for signature.')"
			:close-on-select="false"
			:disabled="loading"
			:loading="loading"
			required
			:options="hashes"
			:show-no-options="false"
			@update:modelValue="saveSignatureHash" />
	</NcSettingsSection>
</template>

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'
import { onMounted, ref } from 'vue'

import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import '@nextcloud/password-confirmation/style.css'

const HASH_ALGORITHMS = ['SHA1', 'SHA256', 'SHA384', 'SHA512', 'RIPEMD160'] as const

type HashAlgorithm = typeof HASH_ALGORITHMS[number]

type OcsConfigResponse = {
	ocs: {
		data: {
			data?: string
		}
	}
}

type OcpGlobal = {
	AppConfig: {
		setValue: (app: string, key: string, value: HashAlgorithm) => void
	}
}

defineOptions({
	name: 'SignatureHashAlgorithm',
})

const selected = ref<HashAlgorithm | ''>('')
const hashes = ref<HashAlgorithm[]>([...HASH_ALGORITHMS])
const loading = ref(false)
const idKey = ref(0)

function isHashAlgorithm(value: unknown): value is HashAlgorithm {
	return HASH_ALGORITHMS.includes(value as HashAlgorithm)
}

async function getData() {
	loading.value = true
	const response = await axios.get(
		generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/signature_hash_algorithm'),
	) as { data: OcsConfigResponse }
	const value = response.data.ocs.data.data
	selected.value = isHashAlgorithm(value) ? value : 'SHA256'
	loading.value = false
}

async function saveSignatureHash() {
	await confirmPassword()
	const normalizedSelected: HashAlgorithm = isHashAlgorithm(selected.value) ? selected.value : 'SHA256'
	;(globalThis as typeof globalThis & { OCP: OcpGlobal }).OCP.AppConfig.setValue('libresign', 'signature_hash_algorithm', normalizedSelected)
	idKey.value += 1
}

onMounted(() => {
	void getData()
})

defineExpose({
	t,
	selected,
	hashes,
	loading,
	idKey,
	getData,
	saveSignatureHash,
})
</script>
