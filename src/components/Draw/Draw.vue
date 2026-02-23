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
				class="draw-signature__tab"
				:class="{ 'draw-signature__tab--active': activeTab === tab.id }"
				variant="tertiary"
				:aria-pressed="activeTab === tab.id"
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

<script>
import { t } from '@nextcloud/l10n'

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

export default {
	name: 'Draw',
	components: {
		NcDialog,
		NcButton,
		NcIconSvgWrapper,
		TextInput,
		Editor,
	},
	props: {
		drawEditor: {
			type: Boolean,
			required: false,
			default: true,
	},
		textEditor: {
			type: Boolean,
			required: false,
			default: false,
	},
		fileEditor: {
			type: Boolean,
			required: false,
			default: false,
	},
		type: {
			type: String,
			required: true,
	},
	},
	setup() {
		const signatureElementsStore = useSignatureElementsStore()
		return {
			signatureElementsStore,
			mdiPencil,
			mdiText,
			mdiCloudUpload,
		}
	},
	data() {
		return {
			mounted: false,
			activeTab: 'draw',
		}
	},
	computed: {
		size() {
			return window.matchMedia('(max-width: 512px)').matches ? 'full' : 'small'
		},
		availableTabs() {
			const tabs = []
			if (this.drawEditor) {
				tabs.push({
					id: 'draw',
					label: this.t('libresign', 'Draw'),
					icon: this.mdiPencil,
				})
			}
			if (this.textEditor) {
				tabs.push({
					id: 'text',
					label: this.t('libresign', 'Text'),
					icon: this.mdiText,
				})
			}
			if (this.fileEditor) {
				tabs.push({
					id: 'file',
					label: this.t('libresign', 'Upload'),
					icon: this.mdiCloudUpload,
				})
			}
			return tabs
		},
	},
	mounted() {
		this.mounted = true
		if (!this.availableTabs.find(tab => tab.id === this.activeTab)) {
			this.activeTab = this.availableTabs[0]?.id || 'draw'
		}
		document.body.classList.add('libresign-modal-open')
		document.documentElement.classList.add('libresign-modal-open')
	},
	beforeUnmount() {
		document.body.classList.remove('libresign-modal-open')
		document.documentElement.classList.remove('libresign-modal-open')
	},
	methods: {
		t,
		close() {
			this.$emit('close')
		},
		async save(base64) {
			this.signatureElementsStore.loadSignatures()
			await this.signatureElementsStore.save(this.type, base64)
			this.$emit('save')
			this.close()
		},
	},
}
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
