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
	<NcSettingsSection :title="title">
		<DownloadBinaries />
		<ConfigureCheck />
		<RootCertificate />
		<SignPage />
		<AllowedGroups />
		<LegalInformation />
	</NcSettingsSection>
</template>

<script>
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection'
import DownloadBinaries from './DownloadBinaries.vue'
import ConfigureCheck from './ConfigureCheck.vue'
import RootCertificate from './RootCertificate.vue'
import SignPage from './SignPage.vue'
import AllowedGroups from './AllowedGroups.vue'
import LegalInformation from './LegalInformation.vue'

export default {
	name: 'Settings',
	components: {
		NcSettingsSection,
		DownloadBinaries,
		ConfigureCheck,
		RootCertificate,
		SignPage,
		AllowedGroups,
		LegalInformation,
	},
	data() {
		return {
			title: t('libresign', 'LibreSign'),
		}
	}
}

</script>
