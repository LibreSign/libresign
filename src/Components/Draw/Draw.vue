<template>
	<NcModal class="draw-signature"
		@close="close">
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
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'
import Editor from './Editor.vue'
import DrawIcon from 'vue-material-design-icons/Draw.vue'
import TextInput from './TextInput.vue'
import SignatureTextIcon from 'vue-material-design-icons/SignatureText.vue'
import FileUpload from './FileUpload.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'
import { useSignatureElementsStore } from '../../store/signatureElements.js'

export default {
	name: 'Draw',
	components: {
		NcModal,
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
	methods: {
		close() {
			this.$emit('close')
		},
		async save(base64) {
			await this.signatureElementsStore.save(this.type, base64)
			this.$emit('save')
			this.close()
		},
	},
}
</script>

<style lang="scss" scoped>
.draw-signature{
	::v-deep .modal-container {
		background-color: red;
		width: unset !important;
		height: unset !important;
	}
	::v-deep .app-sidebar__close {
		display: none;
	}
	::v-deep #app-sidebar-vue {
		width: unset;
	}
}
</style>
