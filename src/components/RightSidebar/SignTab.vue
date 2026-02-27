<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="sign-pdf-sidebar">
		<header>
			<div class="document-status">
				<span class="document-status__label">{{ t('libresign', 'Status') }}</span>
				<span class="document-status__dot" aria-hidden="true" />
				<span class="document-status__text">{{ signStore.document.statusText }}</span>
			</div>
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

import Sign from '../../views/SignPDF/_partials/Sign.vue'

import { loadState } from '@nextcloud/initial-state'

import { FILE_STATUS } from '../../constants.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'

export default {
	name: 'SignTab',
	components: {
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
				params: { uuid: data.signRequestUuid },
				state: { isAfterSigned: true },
			})
		},
		onSigningStarted(payload) {
			this.$router.push({
				name: this.$route.path.startsWith('/p/') ? 'ValidationFileExternal' : 'ValidationFile',
				params: { uuid: payload.signRequestUuid },
				state: { isAfterSigned: false, isAsync: true },
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

	.document-status {
		display: flex;
		align-items: center;
		gap: 6px;
		color: var(--color-text-maxcontrast);
		font-size: 0.85em;

		&__label {
			text-transform: uppercase;
			letter-spacing: 0.05em;
			margin-inline-end: -0.05em; /* compensate trailing letter-spacing in both LTR and RTL */
			font-weight: 600;
		}

		&__dot {
			display: inline-block;
			width: 8px;
			height: 8px;
			border-radius: 50%;
			background-color: var(--color-primary-element);
			flex-shrink: 0;
		}

		&__text {
			color: var(--color-main-text);
			font-weight: 500;
		}
	}
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
