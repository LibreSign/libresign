<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name" :description="description">
		<div class="default-user-folder-content">
			<NcCheckboxRadioSwitch type="switch"
				:checked.sync="customUserFolder">
				{{ t('libresign', 'Customize default user folder') }}
			</NcCheckboxRadioSwitch>
			<div v-if="customUserFolder">
				<NcTextField v-model="value"
					:placeholder="t('libresign', 'Customize default user folder')"
					@update:value="saveDefaultUserFolder" />
			</div>
		</div>
	</NcSettingsSection>
</template>
<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

export default {
	name: 'DefaultUserFolder',
	components: {
		NcSettingsSection,
		NcTextField,
		NcCheckboxRadioSwitch,
	},
	data() {
		return {
			name: t('libresign', 'Customize default user folder'),
			description: t('libresign', 'Name of the folder that will contain the user\'s digital certificate, visible signature images, and other files related to LibreSign.'),
			value: '',
			customUserFolder: false,
		}
	},
	created() {
		this.getData()
	},
	methods: {
		async getData() {
			const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/default_user_folder'))
			this.customUserFolder = !!response.data.ocs.data.data
			this.value = response.data.ocs.data.data || 'LibreSign'
		},
		saveDefaultUserFolder() {
			OCP.AppConfig.setValue('libresign', 'default_user_folder', this.value)
		},
	},
}
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
