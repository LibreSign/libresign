<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="signature-fav">
		<header>
			<h2>
				<slot name="title" />
			</h2>
			<NcActions v-if="isSignatureLoaded" :inline="2">
				<NcActionButton v-if="hasSignature" @click="removeSignature">
					<template #icon>
						<NcIconSvgWrapper :path="mdiDelete" :size="20" />
					</template>
					<!-- TRANSLATORS {type} is the kind of signature element being deleted, e.g. "signature" or "initial" -->
					{{ t('libresign', 'Delete {type}', { type }) }}
				</NcActionButton>
				<NcActionButton @click="edit">
					<template #icon>
						<NcIconSvgWrapper :path="mdiDraw" :size="20" />
					</template>
					<!-- TRANSLATORS {type} is the kind of signature element being edited, e.g. "signature" or "initial" -->
					{{ t('libresign', 'Edit {type}', { type }) }}
				</NcActionButton>
			</NcActions>
		</header>

		<div v-if="hasSignature">
			<PreviewSignature :src="imgSrc"
				:sign-request-uuid="signatureElementsStore.signRequestUuid"
				:alt="t('libresign', 'Current {type}', { type })"
				@loaded="signatureLoaded" />
		</div>
		<div v-else
			class="no-signatures"
			role="button"
			tabindex="0"
			@click="edit"
			@keydown.enter.prevent="edit"
			@keydown.space.prevent="edit">
			<slot name="no-signatures" />
		</div>

		<Draw v-if="isEditing"
			:draw-editor="true"
			:text-editor="true"
			:file-editor="true"
			:type="type"
			@save="save"
			@close="close" />
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import {
	mdiDelete,
	mdiDraw,
} from '@mdi/js'


import { showError, showSuccess } from '@nextcloud/dialogs'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcActions from '@nextcloud/vue/components/NcActions'

import Draw from '../../../components/Draw/Draw.vue'
import PreviewSignature from '../../../components/PreviewSignature/PreviewSignature.vue'

import { useSignatureElementsStore } from '../../../store/signatureElements.js'

defineOptions({
	name: 'Signature',
})

type SignatureType = 'signature' | 'initial'

type SignatureElement = {
	value?: string
	file: {
		url: string
		nodeId: number
	}
}

type SignatureError = string | { message?: string } | null

const props = defineProps<{
	type: SignatureType
}>()

const signatureElementsStore = useSignatureElementsStore()

const isEditing = ref(false)
const isSignatureLoaded = ref(false)
const signatureExists = ref(true)

const hasSignature = computed(() => {
	return signatureElementsStore.hasSignatureOfType(props.type) && signatureExists.value
})

const currentSign = computed(() => signatureElementsStore.signs[props.type] as SignatureElement)

function getErrorMessage(error: SignatureError) {
	if (typeof error === 'string') {
		return error
	}
	return error?.message ?? ''
}

const imgSrc = computed(() => {
	if (currentSign.value?.value?.startsWith('data:')) {
		return currentSign.value.value
	}
	return `${currentSign.value.file.url}&_t=${Date.now()}`
})


function signatureLoaded(status: boolean | Event) {
	const success = typeof status === 'boolean'
		? status
		: !(status instanceof Event && status.type === 'error')
	isSignatureLoaded.value = success
	signatureExists.value = success
}

function edit() {
	isEditing.value = true
}

async function removeSignature() {
	await signatureElementsStore.delete(props.type)
	if (signatureElementsStore.success.length) {
		showSuccess(signatureElementsStore.success)
	} else {
		const message = getErrorMessage(signatureElementsStore.error as SignatureError)
		if (message) {
			showError(message)
		}
	}
}

function close() {
	isEditing.value = false
}

function save() {
	if (signatureElementsStore.success.length) {
		showSuccess(signatureElementsStore.success)
	} else {
		const message = getErrorMessage(signatureElementsStore.error as SignatureError)
		if (message) {
			showError(message)
		}
	}
	close()
}

defineExpose({
	signatureElementsStore,
	mdiDelete,
	mdiDraw,
	isEditing,
	isSignatureLoaded,
	signatureExists,
	hasSignature,
	imgSrc,
	t,
	signatureLoaded,
	edit,
	removeSignature,
	close,
	save,
})
</script>

<style lang="scss" scoped>
.signature-fav{
	margin: 10px;

	header{
		display: flex;
		flex-direction: row;
		justify-content: space-between;

		.icon{
			cursor: pointer;
		}
	}

	img{
		max-width: 250px;
	}

	.no-signatures{
		width: 100%;
		padding: 15px;
		margin: 5px;
		border-radius: 10px;
		background-color: var(--color-main-background);
		box-shadow: 0 2px 9px var(--color-box-shadow);
		cursor: pointer;
		span{
			cursor: inherit;
		}
	}

	h2{
		width: 100%;
		padding-left: 5px;
		border-bottom: 1px solid #000;
		font-size: 1rem;
		font-weight: normal;
	}
}
</style>
