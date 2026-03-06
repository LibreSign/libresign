<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="t('libresign', 'Customize default user folder')"
		:description="t('libresign', 'Name of the folder that will contain the user\'s digital certificate, visible signature images, and other files related to LibreSign.')">
		<div class="default-user-folder-content">
			<NcCheckboxRadioSwitch type="switch"
				v-model="customUserFolder">
				{{ t('libresign', 'Customize default user folder') }}
			</NcCheckboxRadioSwitch>
			<div v-if="customUserFolder">
				<NcTextField v-model="value"
					:placeholder="t('libresign', 'Customize default user folder')"
					@update:modelValue="saveDefaultUserFolder" />
			</div>
		</div>
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

type DefaultFolderResponse = {
	data?: {
		ocs?: {
			data?: {
				data?: string
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
	name: 'DefaultUserFolder',
})

const value = ref('')
const customUserFolder = ref(false)

async function getData() {
	const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/default_user_folder')) as DefaultFolderResponse
	const folder = response.data?.ocs?.data?.data || ''
	customUserFolder.value = !!folder
	value.value = folder || 'LibreSign'
}

function saveDefaultUserFolder() {
	;(globalThis as typeof globalThis & { OCP: OcpGlobal }).OCP.AppConfig.setValue('libresign', 'default_user_folder', value.value)
}

onMounted(() => {
	void getData()
})

defineExpose({
	t,
	value,
	customUserFolder,
	getData,
	saveDefaultUserFolder,
})
</script>
<style scoped>
.default-user-folder-content{
	display: flex;
	flex-direction: column;
}
textarea {
	width: 50%;
	height: 150px;
}
</style>
