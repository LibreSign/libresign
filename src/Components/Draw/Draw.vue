<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="mounted"
		class="draw-signature"
		:size="size"
		:name="t('libresign', 'Customize your signatures')"
		@closing="close">
		<NcAppSidebar active="tab-draw"
			:name="t('libresign', 'Customize your signatures')">
			<NcAppSidebarTab v-if="drawEditor"
				id="tab-draw"
				:name="t('libresign', 'Draw')">
				<template #icon>
					<DrawIcon :size="20" />
				</template>
				<Editor @close="close"
					@save="save" />
			</NcAppSidebarTab>
			<NcAppSidebarTab v-if="textEditor"
				id="tab-text"
				:name="t('libresign', 'Text')">
				<template #icon>
					<SignatureTextIcon :size="20" />
				</template>
				<TextInput @save="save"
					@close="close" />
			</NcAppSidebarTab>
			<NcAppSidebarTab v-if="fileEditor"
				id="tab-upload"
				:name="t('libresign', 'Upload')">
				<template #icon>
					<UploadIcon :size="20" />
				</template>
				<FileUpload @save="save"
					@close="close" />
			</NcAppSidebarTab>
		</NcAppSidebar>

		<div class="content" />
	</NcDialog>
</template>

<script>
import DrawIcon from 'vue-material-design-icons/Draw.vue'
import SignatureTextIcon from 'vue-material-design-icons/SignatureText.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'

import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcDialog from '@nextcloud/vue/components/NcDialog'

import Editor from './Editor.vue'
import FileUpload from './FileUpload.vue'
import TextInput from './TextInput.vue'

import { useSignatureElementsStore } from '../../store/signatureElements.js'

export default {
	name: 'Draw',
	components: {
		NcDialog,
		NcAppSidebar,
		NcAppSidebarTab,
		SignatureTextIcon,
		DrawIcon,
		UploadIcon,
		TextInput,
		Editor,
		FileUpload,
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
		return { signatureElementsStore }
	},
	data() {
		return {
			mounted: false,
		}
	},
	computed: {
		size() {
			return window.matchMedia('(max-width: 512px)').matches ? 'full' : 'small'
		},
	},
	mounted() {
		this.mounted = true
	},
	methods: {
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
.draw-signature{
	::v-deep .app-sidebar-header{
		display: none;
	}
	::v-deep #tab-tab-upload {
		min-width: 350px;
	}
	::v-deep aside {
		border-left: unset;
	}
	::v-deep .app-sidebar__close {
		display: none;
	}
	::v-deep #app-sidebar-vue {
		width: unset;
	}
}
</style>
