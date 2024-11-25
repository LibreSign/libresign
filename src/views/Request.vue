<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container">
		<div id="container-request">
			<header>
				<h1>{{ t('libresign', 'Request Signatures') }}</h1>
				<p v-if="!sidebarStore.isVisible()">
					{{ t('libresign', 'Choose the file to request signatures.') }}
				</p>
			</header>
			<div class="content-request">
				<File v-show="filesStore.selectedNodeId > 0"
					status="0"
					status-text="none" />
				<ReqestPicker v-if="!sidebarStore.isVisible()"
					:inline="true" />
			</div>
		</div>
	</div>
</template>
<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import File from '../Components/File/File.vue'
import ReqestPicker from '../Components/Request/RequestPicker.vue'

import { useFilesStore } from '../store/files.js'
import { useSidebarStore } from '../store/sidebar.js'

export default {
	name: 'Request',
	components: {
		File,
		ReqestPicker,
	},
	setup() {
		const filesStore = useFilesStore()
		const sidebarStore = useSidebarStore()
		return { filesStore, sidebarStore }
	},
	async mounted() {
		subscribe('libresign:visible-elements-saved', this.closeSidebar)
		this.filesStore.disableIdentifySigner()
	},
	beforeUnmount() {
		unsubscribe('libresign:visible-elements-saved')
		this.filesStore.selectFile()
	},
	methods: {
		closeSidebar() {
			this.filesStore.selectFile()
		},
	},
}
</script>

<style lang="scss" scoped>
.container{
	display: flex;
	flex-direction: row;
	justify-content: center;
	align-items: center;
	width: 100%;
	height: 100%;
}

#container-request {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 500px;
	max-width: 100%;
	text-align: center;

	header {
		margin-bottom: 2.5rem;

		h1 {
			font-size: 45px;
			margin-bottom: 1rem;
		}

		p {
			font-size: 15px;
		}
	}

	.content-request{
		display: flex;
		gap: 12px; flex: 1;
		flex-direction: column;
	}
}
</style>
