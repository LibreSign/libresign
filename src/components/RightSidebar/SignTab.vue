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
				<span class="document-status__text">{{ signStore.document?.statusText ?? '' }}</span>
			</div>
		</header>

		<main>
			<div v-if="!signStore.mounted" class="sidebar-loading">
				<p>
					{{ t('libresign', 'Loading …') }}
				</p>
			</div>
			<div v-if="!signEnabled()">
				{{ t('libresign', 'Document not available for signature.') }}
			</div>
			<Sign v-else-if="signStore.mounted"
				@signed="onSigned"
				@signing-started="onSigningStarted" />
		</main>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { getCurrentInstance, onMounted } from 'vue'

import Sign from '../../views/SignPDF/_partials/Sign.vue'

import { loadState } from '@nextcloud/initial-state'

import { FILE_STATUS } from '../../constants.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'

defineOptions({
	name: 'SignTab',
})

type SignTabSigner = {
	me?: boolean
	sign_uuid?: string | null
}

type SignStoreContract = ReturnType<typeof useSignStore>
type SignTabDocument = NonNullable<SignStoreContract['document']>

const signStore = useSignStore()
const sidebarStore = useSidebarStore()

const instance = getCurrentInstance()

function getRouter() {
	return (instance?.appContext.config.globalProperties.$router
		|| instance?.proxy?.$router) as { push: (payload: unknown) => Promise<unknown> | void } | undefined
}

function getRoute() {
	return (instance?.appContext.config.globalProperties.$route
		|| instance?.proxy?.$route) as { path?: string } | undefined
}

function signEnabled() {
	const document = signStore.document as SignTabDocument | undefined
	return FILE_STATUS.ABLE_TO_SIGN === document?.status
		|| FILE_STATUS.PARTIAL_SIGNED === document?.status
}

function getSignRequestUuid() {
	const doc = (signStore.document ?? {}) as SignTabDocument
	const signer = doc.signers?.find(row => row.me) || doc.signers?.[0]
	const fromDoc = [doc.signRequestUuid, doc.sign_request_uuid, doc.signUuid, doc.sign_uuid]
		.find((value): value is string => typeof value === 'string' && value.length > 0)
	const fromSigner = signer?.sign_uuid
	const fromState = loadState<string | null>('libresign', 'sign_request_uuid', null)
	return fromDoc || fromSigner || (typeof fromState === 'string' && fromState.length > 0 ? fromState : null)
}

function getValidationRouteName() {
	const path = getRoute()?.path || ''
	return path.startsWith('/p/') ? 'ValidationFileExternal' : 'ValidationFile'
}

function onSigned(data: { signRequestUuid: string }) {
	getRouter()?.push({
		name: getValidationRouteName(),
		params: { uuid: data.signRequestUuid },
		state: { isAfterSigned: true },
	})
}

function onSigningStarted(payload: { signRequestUuid: string }) {
	getRouter()?.push({
		name: getValidationRouteName(),
		params: { uuid: payload.signRequestUuid },
		state: { isAfterSigned: false, isAsync: true },
	})
}

onMounted(() => {
	if (signStore.document?.status === FILE_STATUS.SIGNING_IN_PROGRESS) {
		const signRequestUuid = getSignRequestUuid()
		if (signRequestUuid) {
			onSigningStarted({ signRequestUuid })
		}
	}
})

defineExpose({
	signStore,
	sidebarStore,
	signEnabled,
	getSignRequestUuid,
	onSigned,
	onSigningStarted,
})
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
