<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="t('libresign', 'Expiration rules')">
		<p>
			{{ t('libresign', 'Rules for controlling the expiration of a request to sign a file.') }}
		</p>
		<NcCheckboxRadioSwitch type="switch"
			v-model="enableMaximumValidity"
			@update:model-value="saveMaximumValidity">
			{{ t('libresign', 'Maximum validity') }}
		</NcCheckboxRadioSwitch>
		<fieldset v-show="enableMaximumValidity" id="settings-maximum_validity" class="sharing__sub-section">
			{{ t('libresign', 'Maximum validity in seconds of a request to sign.') }}
			<NcTextField v-model="maximumValidity"
				type="number"
				class="sharing__input"
				:label="t('libresign', 'Maximum validity')"
				:placeholder="t('libresign', 'Maximum validity')"
				@update:modelValue="saveMaximumValidity" />
		</fieldset>
		<NcCheckboxRadioSwitch type="switch"
			v-model="enableRenewalInterval"
			@update:model-value="saveRenewalInterval">
			{{ t('libresign', 'Renewal interval') }}
		</NcCheckboxRadioSwitch>
		<fieldset v-show="enableRenewalInterval" id="settings-renewal-interval" class="sharing__sub-section">
			{{ t('libresign', 'Renewal interval in seconds of a subscription request. When accessing the link, you will be asked to renew the link.') }}
			<NcTextField v-model="renewalInterval"
				type="number"
				class="sharing__input"
				:label="t('libresign', 'Renewal interval')"
				:placeholder="t('libresign', 'Renewal interval')"
				@update:modelValue="saveRenewalInterval" />
		</fieldset>
		<fieldset id="settings-certificate-validity" class="sharing__sub-section">
			{{ t('libresign', 'The length of time for which the generated certificate will be valid, in days.') }}
			<NcTextField v-model="expiryInDays"
				type="number"
				class="sharing__input"
				:label="t('libresign', 'Expiration in days')"
				:placeholder="t('libresign', 'Expiration in days')"
				@update:modelValue="saveExpiryInDays" />
		</fieldset>
	</NcSettingsSection>
</template>
<script setup lang="ts">
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { onMounted, ref } from 'vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

type OcsConfigResponse = {
	data?: {
		ocs?: {
			data?: {
				data?: string | number
			}
		}
	}
}

type OcpGlobal = {
	AppConfig: {
		setValue: (app: string, key: string, value: string) => void
	}
}

defineOptions({
	name: 'ExpirationRules',
})

const paternValidadeUrl = ref('https://validador.librecode.coop/')
const enableMaximumValidity = ref(false)
const maximumValidity = ref('0')
const enableRenewalInterval = ref(false)
const renewalInterval = ref('0')
const expiryInDays = ref<string | number>(0)
const url = ref<string | null>(null)

async function getMaximumValidity() {
	const response = await axios.get(
		generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/maximum_validity'),
	) as OcsConfigResponse
	maximumValidity.value = Number(response.data?.ocs?.data?.data).toString()
	enableMaximumValidity.value = Number(maximumValidity.value) > 0
}

async function saveMaximumValidity() {
	if (!enableMaximumValidity.value) {
		maximumValidity.value = '0'
	}
	;(globalThis as typeof globalThis & { OCP: OcpGlobal }).OCP.AppConfig.setValue(
		'libresign',
		'maximum_validity',
		String(Number(maximumValidity.value)),
	)
}

async function getRenewalInterval() {
	const response = await axios.get(
		generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/renewal_interval'),
	) as OcsConfigResponse
	renewalInterval.value = Number(response.data?.ocs?.data?.data).toString()
	enableRenewalInterval.value = Number(renewalInterval.value) > 0
}

async function saveRenewalInterval() {
	if (!enableRenewalInterval.value) {
		renewalInterval.value = '0'
	}
	;(globalThis as typeof globalThis & { OCP: OcpGlobal }).OCP.AppConfig.setValue(
		'libresign',
		'renewal_interval',
		String(Number(renewalInterval.value)),
	)
}

async function getExpiryInDays() {
	const response = await axios.get(
		generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/expiry_in_days'),
	) as OcsConfigResponse
	expiryInDays.value = Number(response.data?.ocs?.data?.data).toString()
	if (Number(expiryInDays.value) === 0) {
		expiryInDays.value = 365
	}
}

async function saveExpiryInDays() {
	if (!expiryInDays.value) {
		expiryInDays.value = 365
	}
	;(globalThis as typeof globalThis & { OCP: OcpGlobal }).OCP.AppConfig.setValue(
		'libresign',
		'expiry_in_days',
		String(Number(expiryInDays.value)),
	)
}

async function getData() {
	await Promise.all([
		getMaximumValidity(),
		getRenewalInterval(),
		getExpiryInDays(),
	])
}

onMounted(() => {
	void getData()
})

defineExpose({
	t,
	paternValidadeUrl,
	enableMaximumValidity,
	maximumValidity,
	enableRenewalInterval,
	renewalInterval,
	expiryInDays,
	url,
	getData,
	getMaximumValidity,
	saveMaximumValidity,
	getRenewalInterval,
	saveRenewalInterval,
	getExpiryInDays,
	saveExpiryInDays,
})
</script>
<style scoped>

input{
	width: 100%;
}

</style>
