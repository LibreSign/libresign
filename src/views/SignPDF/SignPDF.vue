<template>
	<div class="main-view">
		<TopBar :sidebar-toggle="true" />
		<PdfEditor v-if="mounted"
			ref="pdfEditor"
			width="100%"
			height="100%"
			:file-src="signStore.document.url"
			:read-only="true"
			@pdf-editor:end-init="updateSigners" />
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'

import PdfEditor from '../../Components/PdfEditor/PdfEditor.vue'
import TopBar from '../../Components/TopBar/TopBar.vue'

import { useFilesStore } from '../../store/files.js'
import { useSignStore } from '../../store/sign.js'
import { useSignMethodsStore } from '../../store/signMethods.js'

export default {
	name: 'SignPDF',
	components: {
		TopBar,
		PdfEditor,
	},
	setup() {
		const signStore = useSignStore()
		const fileStore = useFilesStore()
		const signMethodsStore = useSignMethodsStore()
		return { signStore, fileStore, signMethodsStore }
	},
	data() {
		return {
			mounted: false,
		}
	},
	mounted() {
		if (this.$route.name === 'SignPDFExternal') {
			this.initSignExternal()
		} else if (this.$route.name === 'SignPDF') {
			this.initSignInternal()
		}
		this.signStore.errors.forEach(error => {
			showError(error)
		})
	},
	methods: {
		initSignExternal() {
			this.signStore.initFromState()
			if (!this.signStore.document.uuid) {
				this.signStore.document.uuid = this.$route.params.uuid
			}
			this.mounted = true
		},
		async initSignInternal() {
			const files = await this.fileStore.getAllFiles({
				signer_uuid: this.$route.params.uuid,
			})
			for (const nodeId in files) {
				const signer = files[nodeId].signers.find(row => row.me) || {}
				if (Object.keys(signer).length > 0) {
					this.signStore.setDocumentToSign(files[nodeId])
					this.fileStore.selectedNodeId = nodeId
					this.mounted = true
					return true
				}
			}
		},
		updateSigners(data) {
			this.signStore.document.signers.forEach(signer => {
				if (signer.visibleElements.length > 0) {
					signer.visibleElements.forEach(element => {
						const object = structuredClone(signer)
						object.readOnly = true
						element.coordinates.ury = Math.round(data.measurement[element.coordinates.page].height)
							- element.coordinates.ury
						object.element = element
						this.$refs.pdfEditor.addSigner(object)
					})
				}
			})
			this.signStore.mounted = true
		},
	},
}
</script>

<style lang="scss">
.bg-gray-100 {
	all: unset;
}
</style>
<style lang="scss" scoped>
.main-view {
	height: 100%;
	width: 100%;
	display: flex;
	flex-direction: column;
	align-content: space-between;
	position: relative;
}
</style>
