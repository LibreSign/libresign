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
	<NcContent app-name="libresign">
		<NcAppNavigation :class="{'icon-loading' : loading }">
			<template #list>
				<NcAppNavigationItem v-if="back_to_signature"
					class="back_to_signature"
					icon="icon-history"
					:name="t('libresign', 'Back to sign')"
					@click="goToSign" />

				<NcAppNavigationItem v-if="canRequestSign"
					id="request-files"
					:to="{name: 'requestFiles'}"
					:name="t('libresign', 'Request')">
					<template #icon>
						<FileSignIcon :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem id="sign-files"
					:to="{ name: 'signFiles' }"
					:name="t('libresign', 'Files')">
					<template #icon>
						<FolderIcon :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem id="validation"
					:to="{name: 'validation'}"
					:name="t('libresign', 'Validate')">
					<template #icon>
						<FileCheckIcon :size="20" />
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem v-if="config.identificationDocumentsFlow && config.isApprover"
					:to="{name: 'DocsAccountValidation'}"
					:name="t('libresign', 'Documents Validation')"
					icon="icon-user" />
			</template>
			<template #footer>
				<NcAppNavigationSettings :title="t('libresign', 'Settings')">
					<CroppedLayoutSettings />
				</NcAppNavigationSettings>
			</template>
		</NcAppNavigation>
		<NcAppContent :class="{'icon-loading' : loading }">
			<router-view v-if="!loading" :key="$route.name " :loading.sync="loading" />
			<NcEmptyContent v-if="isRoot" :description="t('libresign', 'LibreSign, digital signature app for Nextcloud.')">
				<template #icon>
					<img :src="LogoLibreSign">
				</template>
			</NcEmptyContent>
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppNavigationSettings from '@nextcloud/vue/dist/Components/NcAppNavigationSettings.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import LogoLibreSign from './../img/logo-gray.svg'
import CroppedLayoutSettings from './Components/Settings/CroppedLayoutSettings.vue'
import { loadState } from '@nextcloud/initial-state'
import FileSignIcon from 'vue-material-design-icons/FileSign.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import FileCheckIcon from 'vue-material-design-icons/FileCheck.vue'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationSettings,
		NcAppContent,
		NcEmptyContent,
		CroppedLayoutSettings,
		FileSignIcon,
		FolderIcon,
		FileCheckIcon,
	},
	data() {
		return {
			canRequestSign: loadState('libresign', 'can_request_sign'),
			config: loadState('libresign', 'config', {
				hasSignatureFile: false,
				identificationDocumentsFlow: false,
				isApprover: false,
				phoneNumber: '',
			}),
			loading: false,
			LogoLibreSign,
		}
	},
	computed: {
		back_to_signature() {
			return this.$route.query._back_to_signature
		},
		isRoot() {
			return this.$route.path === '/'
		},
	},
	methods: {
		goToSign() {
			const route = this.$router.resolve({ name: 'SignPDF', params: { uuid: this.back_to_signature } })

			window.location = route.href
		},
	},
}
</script>

<style lang="scss">
.app-libresign {
	.app-navigation {
		.back_to_signature
			.app-navigation-entry__title {
			color: var(--color-warning, #eca700);
		}
		.app-navigation-entry.active {
			background-color: var(--color-primary-element) !important;
			.app-navigation-entry-link{
				color: var(--color-primary-element-text) !important;
			}
		}
	}
}

.app-content {
	.empty-content {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 100%;
		height: 70%;
		margin-top: unset !important;

		margin-top: 10vh;
		p {
			opacity: .6;
		}

		&__icon {
			width: 400px !important;
			height: unset !important;
		}
	}
}
</style>
