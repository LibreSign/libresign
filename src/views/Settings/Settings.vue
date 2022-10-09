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
		<ConfigureCheck />
		<DownloadBinaries />
		<RootCertificate />
		<UrlValidation />
		<AllowedGroups />
		<LegalInformation />
	</NcSettingsSection>
</template>

<script>
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection'
import ConfigureCheck from './ConfigureCheck.vue'
import DownloadBinaries from './DownloadBinaries.vue'
import RootCertificate from './RootCertificate.vue'
import UrlValidation from './UrlValidation.vue'
import AllowedGroups from './AllowedGroups.vue'
import LegalInformation from './LegalInformation.vue'

export default {
	name: 'Settings',
	components: {
		NcSettingsSection,
		ConfigureCheck,
		DownloadBinaries,
		RootCertificate,
		UrlValidation,
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
