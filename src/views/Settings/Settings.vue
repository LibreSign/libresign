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
	<NcSettingsSection :name="name">
		<CertificateEngine />
		<DownloadBinaries />
		<ConfigureCheck />
		<RootCertificateCfssl />
		<RootCertificateOpenSsl />
		<IdentificationFactors />
		<ExpirationRules />
		<Validation />
		<AllowedGroups />
		<LegalInformation />
		<IdentificationDocuments />
		<CollectMetadata />
		<DefaultUserFolder />
	</NcSettingsSection>
</template>

<script>
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'

import AllowedGroups from './AllowedGroups.vue'
import CertificateEngine from './CertificateEngine.vue'
import CollectMetadata from './CollectMetadata.vue'
import ConfigureCheck from './ConfigureCheck.vue'
import DefaultUserFolder from './DefaultUserFolder.vue'
import DownloadBinaries from './DownloadBinaries.vue'
import ExpirationRules from './ExpirationRules.vue'
import IdentificationDocuments from './IdentificationDocuments.vue'
import IdentificationFactors from './IdentificationFactors.vue'
import LegalInformation from './LegalInformation.vue'
import RootCertificateCfssl from './RootCertificateCfssl.vue'
import RootCertificateOpenSsl from './RootCertificateOpenSsl.vue'
import Validation from './Validation.vue'

export default {
	name: 'Settings',
	components: {
		NcSettingsSection,
		CertificateEngine,
		DownloadBinaries,
		ConfigureCheck,
		RootCertificateCfssl,
		RootCertificateOpenSsl,
		IdentificationFactors,
		ExpirationRules,
		Validation,
		AllowedGroups,
		LegalInformation,
		IdentificationDocuments,
		CollectMetadata,
		DefaultUserFolder,
	},
	data() {
		return {
			name: t('libresign', 'LibreSign'),
		}
	},
}

</script>
