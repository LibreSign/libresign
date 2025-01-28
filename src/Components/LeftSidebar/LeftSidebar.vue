<!--
- @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
-
- @author Vitor Mattos <vitor@php.rio>
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
-->

<template>
	<NcAppNavigation v-if="showLeftSidebar">
		<template #list>
			<NcAppNavigationItem v-if="canRequestSign"
				id="request-files"
				:to="{name: 'requestFiles'}"
				:name="t('libresign', 'Request')"
				@click="unselectFile">
				<template #icon>
					<FileSignIcon :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem id="fileslist"
				:to="{ name: 'fileslist' }"
				:name="t('libresign', 'Files')"
				@click="unselectFile">
				<template #icon>
					<FolderIcon :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem id="validation"
				:to="{name: 'validation'}"
				:name="t('libresign', 'Validate')"
				@click="unselectFile">
				<template #icon>
					<FileCheckIcon :size="20" />
				</template>
			</NcAppNavigationItem>

			<NcAppNavigationItem v-if="config.identificationDocumentsFlow && config.isApprover"
				:to="{name: 'DocsAccountValidation'}"
				:name="t('libresign', 'Documents Validation')">
				<template #icon>
					<AccountCheckIcon :size="20" />
				</template>
			</NcAppNavigationItem>
		</template>
		<template #footer>
			<NcAppNavigationSettings :title="t('libresign', 'Settings')">
				<Settings />
			</NcAppNavigationSettings>
		</template>
	</NcAppNavigation>
</template>

<script>
import AccountCheckIcon from 'vue-material-design-icons/AccountCheck.vue'
import FileCheckIcon from 'vue-material-design-icons/FileCheck.vue'
import FileSignIcon from 'vue-material-design-icons/FileSign.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'

import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'

import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppNavigationSettings from '@nextcloud/vue/dist/Components/NcAppNavigationSettings.js'

import Settings from '../Settings/Settings.vue'

import { useFilesStore } from '../../store/files.js'

export default {
	name: 'LeftSidebar',
	components: {
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationSettings,
		AccountCheckIcon,
		FileCheckIcon,
		FolderIcon,
		FileSignIcon,
		Settings,
	},
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
	data() {
		return {
			canRequestSign: loadState('libresign', 'can_request_sign', false),
			config: loadState('libresign', 'config', {
				identificationDocumentsFlow: false,
				isApprover: false,
			}),
		}
	},
	computed: {
		showLeftSidebar() {
			if (this.$route.name === 'Incomplete'
				|| this.$route.name === 'IncompleteExternal'
				|| !getCurrentUser()
				|| this.$route.path.startsWith('/p/')
				|| this.$route.path.startsWith('/validation/') // short validation url
			) {
				return false
			}
			return true
		},
	},
	methods: {
		unselectFile() {
			this.filesStore.selectFile()
		},
		goToSign() {
			const route = this.$router.resolve({ name: 'SignPDF' })

			window.location = route.href
		},
	},
}
</script>
