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
	<SettingsSection
		:title="title">
		<AdminFormLibresign />
		<UrlValidation />
		<AllowedGroups />
		<div class="settings-section">
			<h2>{{ t('libresign', 'Legal information') }}</h2>
			<div class="legal-information-content">
				<span>{{ t('libresign', 'This information will appear on the validation page') }}</span>
				<Textarea
					v-model="legalInformation"
					:placeholder="t('libresign', 'Legal Information')"
					@input="saveLegalInformation" />
			</div>
		</div>
	</SettingsSection>
</template>

<script>
import AdminFormLibresign from './AdminFormLibresign'
import AllowedGroups from './AllowedGroups'
import UrlValidation from './UrlValidation'
import Textarea from '../Components/Textarea/Textarea'
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'Settings',
	components: {
		AdminFormLibresign,
		SettingsSection,
		UrlValidation,
		AllowedGroups,
		Textarea,
	},
	data() {
		return {
			title: t('libresign', 'LibreSign'),
			legalInformation: '',
		}
	},
	created() {
		this.getData()
	},
	methods: {
		async getData() {
			const response = await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1', 2) + 'config/apps/libresign/legal_information', {})
			this.legalInformation = response.data.ocs.data.data
		},
		saveLegalInformation() {
			OCP.AppConfig.setValue('libresign', 'legal_information', this.legalInformation)
		},
	},
}

</script>
<style scoped>
#libresign-admin-settings {
	width: 100vw;
	padding: 20px;
	padding-top: 70px;
	display: flex;
	flex-direction: column;
	flex-grow: 1;
	align-items: center;
}

.legal-information-content{
	display: flex;
	flex-direction: column;
}

textarea {
	width: 50%;
	height: 150px;
}
</style>
