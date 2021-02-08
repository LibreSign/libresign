<template>
	<SettingsSection :title="title" :description="description">
		<MulutiSelect
			v-model="groupsSelected"
			:options="groups"
			:close-on-select="false"
			:multiple="true"
			:tag-width="300"
			@change="saveGroups" />
	</SettingsSection>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import MulutiSelect from '@nextcloud/vue/dist/Components/Multiselect'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'AllowedGroups',
	components: {
		SettingsSection,
		MulutiSelect,
	},

	data: () => ({
		title: t('libresign', 'Webhook'),
		description: t('libresign', 'Selecionar grupos autorizados.'),
		groupsSelected: [],
		groups: [],
	}),

	created() {
		this.get()
	},

	methods: {
		getData() {
			const list = OC.AppConfig.getValue('libresign', 'webhook_authorized')
			// eslint-disable-next-line
			console.log(list)
		},
		saveGroups() {
			const listOfInputGroupsSelected = JSON.stringify(this.groupsSelected)
			OCP.AppConfig.setValue('libresign', 'webhook_authorized', listOfInputGroupsSelected)
		},

		async get() {
			const response = await axios.get(generateOcsUrl('cloud/groups?', 3))
			this.groups = response.data.ocs.data.groups
		},
	},

}
</script>
