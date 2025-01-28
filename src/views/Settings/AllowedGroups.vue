<!--
- @copyright Copyright (c) 2021 Lyseon Tech <contato@lt.coop.br>
-
- @author Lyseon Tech <contato@lt.coop.br>
- @author Vinicios Gomes <viniciusgomesvaian@gmail.com>
-
- @license GNU AGPL version 3 or any later version
-
- This program is free software: you can redistribute it and/or modify
- it under the terms of the GNU Affero General Public License as
- published by the Free Software Foundation, either version 3 of the
- License, or (at your option) any later version.
-
- This program is distributed in the hope that it will be useful,
- but WITHOUT ANY WARRANTY; without even the implied warranty of
- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
- GNU Affero General Public License for more details.
-
- You should have received a copy of the GNU Affero General Public License
- along with this program.  If not, see <http://www.gnu.org/licenses/>.
-
-->

<template>
	<NcSettingsSection :name="name" :description="description">
		<NcSelect :key="idKey"
			v-model="groupsSelected"
			label="displayname"
			:no-wrap="false"
			:aria-label-combobox="description"
			:close-on-select="false"
			:disabled="loadingGroups"
			:loading="loadingGroups"
			:multiple="true"
			:options="groups"
			:searchable="true"
			:show-no-options="false"
			@search-change="searchGroup"
			@input="saveGroups" />
	</NcSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'

import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'

import logger from '../../logger.js'

import '@nextcloud/password-confirmation/dist/style.css'

export default {
	name: 'AllowedGroups',
	components: {
		NcSettingsSection,
		NcSelect,
	},

	data: () => ({
		name: t('libresign', 'Allow request to sign'),
		description: t('libresign', 'Select authorized groups that can request to sign documents. Admin group is the default group and don\'t need to be defined.'),
		groupsSelected: [],
		groups: [],
		loadingGroups: false,
		idKey: 0,
	}),

	async created() {
		await this.searchGroup('')
		await this.getData()
	},

	methods: {
		async getData() {
			this.loadingGroups = true
			await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/groups_request_sign'),
			)
				.then(({ data }) => {
					const groupsSelected = JSON.parse(data.ocs.data.data)
					if (!groupsSelected) {
						return
					}
					this.groupsSelected = this.groups.filter(group => {
						return groupsSelected.indexOf(group.id) !== -1
					})
				})
				.catch((error) => logger.debug('Could not fetch groups_request_sign', { error }))
			this.loadingGroups = false
		},

		async saveGroups() {
			await confirmPassword()

			const listOfInputGroupsSelected = JSON.stringify(this.groupsSelected.map((g) => {
				if (typeof g === 'object') {
					return g.id
				}
				return g
			}))
			OCP.AppConfig.setValue('libresign', 'groups_request_sign', listOfInputGroupsSelected)
			this.idKey += 1
		},

		async searchGroup(query) {
			this.loadingGroups = true
			await axios.get(generateOcsUrl('cloud/groups/details'), {
				search: query,
				limit: 20,
				offset: 0,
			})
				.then(({ data }) => {
					this.groups = data.ocs.data.groups.sort(function(a, b) {
						return a.displayname.localeCompare(b.displayname)
					})
				})
				.catch((error) => logger.debug('Could not search by groups', { error }))
			this.loadingGroups = false
		},
	},

}
</script>
