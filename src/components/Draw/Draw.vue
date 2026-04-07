<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="mounted"
		class="draw-signature"
		size="small"
		:name="t('libresign', 'Customize your signatures')"
		@closing="close">
		<div v-if="availableTabs.length > 1" class="draw-signature__tabs" role="tablist" :aria-label="t('libresign', 'Signature type')">
			<NcButton
				v-for="tab in availableTabs"
				:key="tab.id"
				role="tab"
				class="draw-signature__tab"
				:class="{ 'draw-signature__tab--active': activeTab === tab.id }"
				variant="tertiary"
				:aria-selected="activeTab === tab.id"
				@click="activeTab = tab.id">
				<NcIconSvgWrapper :path="tab.icon" :size="18" />
				<span class="draw-signature__tab-label">{{ tab.label }}</span>
			</NcButton>
		</div>

		<Editor v-if="activeTab === 'draw' && drawEditor" @close="close" @save="save" />
		<TextInput v-if="activeTab === 'text' && textEditor" @save="save" @close="close" />
		<FileUpload v-if="activeTab === 'file' && fileEditor" @save="save" @close="close" />

		<div class="content" />
	</NcDialog>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

import {
	mdiCloudUpload,
	mdiPencil,
	mdiText,
} from '@mdi/js'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import Editor from './Editor.vue'
import FileUpload from './FileUpload.vue'
import TextInput from './TextInput.vue'

import { useSignatureElementsStore } from '../../store/signatureElements.js'

defineOptions({
	name: 'Draw',
})

type AvailableTab = {
	id: string
	label: string
	icon: string
}

const props = withDefaults(defineProps<{
	drawEditor?: boolean
	textEditor?: boolean
	fileEditor?: boolean
	type: string
}>(), {
	drawEditor: true,
	textEditor: false,
	fileEditor: false,
})

const emit = defineEmits<{
	(event: 'close'): void
	(event: 'save'): void
}>()

const signatureElementsStore = useSignatureElementsStore()
const mounted = ref(false)
const activeTab = ref('draw')

const size = computed(() => {
	return window.matchMedia('(max-width: 512px)').matches ? 'full' : 'small'
})

const availableTabs = computed<AvailableTab[]>(() => {
	const tabs: AvailableTab[] = []
	if (props.drawEditor) {
		tabs.push({
			id: 'draw',
			label: t('libresign', 'Draw'),
			icon: mdiPencil,
		})
	}
	if (props.textEditor) {
		tabs.push({
			id: 'text',
			label: t('libresign', 'Text'),
			icon: mdiText,
		})
	}
	if (props.fileEditor) {
		tabs.push({
			id: 'file',
			label: t('libresign', 'Upload'),
			icon: mdiCloudUpload,
		})
	}
	return tabs
})

function close() {
	emit('close')
}

async function save(base64: string) {
	await signatureElementsStore.save(props.type, base64)
	await nextTick()
	emit('save')
	close()
}

onMounted(() => {
	mounted.value = true
	if (!availableTabs.value.find((tab) => tab.id === activeTab.value)) {
		activeTab.value = availableTabs.value[0]?.id || 'draw'
	}
	document.body.classList.add('libresign-modal-open')
	document.documentElement.classList.add('libresign-modal-open')
})

onBeforeUnmount(() => {
	document.body.classList.remove('libresign-modal-open')
	document.documentElement.classList.remove('libresign-modal-open')
})

defineExpose({
	t,
	props,
	type: props.type,
	signatureElementsStore,
	mdiPencil,
	mdiText,
	mdiCloudUpload,
	mounted,
	activeTab,
	size,
	availableTabs,
	close,
	save,
})
</script>

<style lang="scss" scoped>
@media (max-width: 512px) {
	:global(.dialog__modal.draw-signature .modal-header) {
		display: none !important;
	}

	:global(.dialog__modal.draw-signature .modal-wrapper--small > .modal-container) {
		position: fixed !important;
		top: 0 !important;
		overscroll-behavior: contain;
	}
}

:global(.dialog__modal.draw-signature .dialog__content) {
	display: flex;
	flex-direction: column;
	min-height: 0;
}

:global(body.libresign-modal-open) {
	overflow: hidden;
	touch-action: none;
}

.draw-signature {
	&__tabs {
		display: flex;
		gap: 6px;
		padding: 6px 12px 2px;
		align-items: center;
		justify-content: center;
		flex-wrap: wrap;
	}

	&__tab {
		display: inline-flex;
		gap: 6px;
		align-items: center;

		&--active {
			background-color: var(--color-primary-element-light) !important;
			color: var(--color-primary-text) !important;
		}
	}

	&__tab-label {
		font-weight: 600;
	}
}
</style>
