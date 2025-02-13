<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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

import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationSettings from '@nextcloud/vue/components/NcAppNavigationSettings'

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
