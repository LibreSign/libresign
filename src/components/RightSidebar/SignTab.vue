<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="sign-pdf-sidebar">
		<header>
			<Chip>
				{{ signStore.document.statusText }}
			</Chip>
		</header>

		<main>
			<div v-if="!signStore.mounted" class="sidebar-loading">
				<p>
					{{ t('libresign', 'Loading …') }}
				</p>
			</div>
			<div v-if="!signEnabled">
				{{ t('libresign', 'Document not available for signature.') }}
			</div>
			<Sign v-else-if="signStore.mounted"
				@signed="onSigned"
				@signing-started="onSigningStarted" />
		</main>
	</div>
</template>

<script>
import Chip from '../../components/Chip.vue'
import Sign from '../../views/SignPDF/_partials/Sign.vue'

import { FILE_STATUS } from '../../constants.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'

export default {
	name: 'SignTab',
	components: {
		Chip,
		Sign,
	},
	setup() {
		const signStore = useSignStore()
		const sidebarStore = useSidebarStore()
		return { signStore, sidebarStore }
	},
	mounted() {
		// If signing is already in progress, redirect to validation/progress view
		if (this.signStore.document?.status === FILE_STATUS.SIGNING_IN_PROGRESS) {
			this.onSigningStarted({ fileUuid: this.signStore.document.uuid })
		}
	},
	methods: {
		signEnabled() {
			return FILE_STATUS.ABLE_TO_SIGN === this.signStore.document.status
				|| FILE_STATUS.PARTIAL_SIGNED === this.signStore.document.status
		},
		onSigned(data) {
			this.$router.push({
				name: this.$route.path.startsWith('/p/') ? 'ValidationFileExternal' : 'ValidationFile',
				params: {
					uuid: data.file.uuid,
					isAfterSigned: true,
				},
			})
		},
		onSigningStarted(payload) {
			// Async signing started, redirect to validation/progress page
			this.$router.push({
				name: this.$route.path.startsWith('/p/') ? 'ValidationFileExternal' : 'ValidationFile',
				params: {
					uuid: payload.fileUuid,
					isAfterSigned: false,
					isAsync: true,
				},
			})
		},
	},
}
</script>

<style lang="scss" scoped>
header {
	text-align: center;
	width: 100%;
}
@media (min-width: 513px) {
	header {
		margin-top: 1em;
		margin-bottom: 3em;
	}
}
main {
	flex-direction: column;
	align-items: center;
	width: 100%;
	.sidebar-loading {
		text-align: center;
	}
}
</style>
