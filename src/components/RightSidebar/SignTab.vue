<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="sign-pdf-sidebar">
		<header>
			<NcChip :text="signStore.document.statusText" variant="primary" no-close />
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
import { t } from '@nextcloud/l10n'

import NcChip from '@nextcloud/vue/components/NcChip'
import Sign from '../../views/SignPDF/_partials/Sign.vue'

import { loadState } from '@nextcloud/initial-state'

import { FILE_STATUS } from '../../constants.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'

export default {
	name: 'SignTab',
	components: {
		NcChip,
		Sign,
	},
	setup() {
		const signStore = useSignStore()
		const sidebarStore = useSidebarStore()
		return { signStore, sidebarStore }
	},
	mounted() {
		if (this.signStore.document?.status === FILE_STATUS.SIGNING_IN_PROGRESS) {
			const signRequestUuid = this.getSignRequestUuid()
			if (signRequestUuid) {
				this.onSigningStarted({ signRequestUuid })
			}
		}
	},
	methods: {
		t,
		signEnabled() {
			return FILE_STATUS.ABLE_TO_SIGN === this.signStore.document.status
				|| FILE_STATUS.PARTIAL_SIGNED === this.signStore.document.status
		},
		getSignRequestUuid() {
			const doc = this.signStore.document || {}
			const signer = doc.signers?.find(row => row.me) || doc.signers?.[0] || {}
			const fromDoc = doc.signRequestUuid || doc.sign_request_uuid || doc.signUuid || doc.sign_uuid
			const fromSigner = signer.sign_uuid
			return fromDoc || fromSigner || loadState('libresign', 'sign_request_uuid', null)
		},
		onSigned(data) {
			this.$router.push({
				name: this.$route.path.startsWith('/p/') ? 'ValidationFileExternal' : 'ValidationFile',
				params: {
					uuid: data.signRequestUuid,
					isAfterSigned: true,
	},
			})
		},
		onSigningStarted(payload) {
			this.$router.push({
				name: this.$route.path.startsWith('/p/') ? 'ValidationFileExternal' : 'ValidationFile',
				params: {
					uuid: payload.signRequestUuid,
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
	display: flex;
	justify-content: center;
	align-items: center;
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
