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
				@signed="onSigned" />
		</main>
	</div>
</template>

<script>
import Chip from '../../Components/Chip.vue'
import Sign from '../../views/SignPDF/_partials/Sign.vue'

import { SIGN_STATUS } from '../../domains/sign/enum.js'
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
	methods: {
		signEnabled() {
			return SIGN_STATUS.ABLE_TO_SIGN === this.signStore.document.status
				|| SIGN_STATUS.PARTIAL_SIGNED === this.signStore.document.status
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
	},
}
</script>

<style lang="scss" scoped>
header {
	text-align: center;
	width: 100%;
	margin-top: 1em;
	margin-bottom: 3em;
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
