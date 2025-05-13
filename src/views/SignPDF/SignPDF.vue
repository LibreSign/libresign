<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="main-view">
		<TopBar :sidebar-toggle="true" />
		<PdfEditor v-if="mounted && !signStore.errors.length && pdfBlob"
			ref="pdfEditor"
			width="100%"
			height="100%"
			:file="pdfBlob"
			:read-only="true"
			@pdf-editor:end-init="updateSigners" />
		<NcNoteCard v-for="(error, index) in signStore.errors"
			:key="index"
			:heading="error.title || ''"
			type="error">
			{{ error.message }}
		</NcNoteCard>
	</div>
</template>

<script>
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import PdfEditor from '../../Components/PdfEditor/PdfEditor.vue'
import TopBar from '../../Components/TopBar/TopBar.vue'

import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'
import { useSignMethodsStore } from '../../store/signMethods.js'

export default {
	name: 'SignPDF',
	components: {
		NcNoteCard,
		TopBar,
		PdfEditor,
	},
	setup() {
		const signStore = useSignStore()
		const fileStore = useFilesStore()
		const sidebarStore = useSidebarStore()
		const signMethodsStore = useSignMethodsStore()
		return { signStore, fileStore, sidebarStore, signMethodsStore }
	},
	data() {
		return {
			mounted: false,
			pdfBlob: null,
		}
	},
	async created() {
		if (this.$route.name === 'SignPDFExternal') {
			await this.initSignExternal()
		} else if (this.$route.name === 'SignPDF') {
			await this.initSignInternal()
		}
	},
	methods: {
		async initSignExternal() {
			this.signStore.initFromState()
			if (!this.signStore.document.uuid) {
				this.signStore.document.uuid = this.$route.params.uuid
			}
			await this.fetchPdfAsBlob(this.signStore.document.url)
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
					await this.fetchPdfAsBlob(this.signStore.document.url)
					this.mounted = true
					return
				}
			}
		},
		async fetchPdfAsBlob(url) {
			const response = await fetch(url)
			const contentType = response.headers.get('Content-Type') || ''

			if (contentType.includes('application/json')) {
				const data = await response.json()
				this.sidebarStore.hideSidebar()
				if (data?.errors?.[0]?.message.length > 0) {
					this.signStore.errors = data.errors
					return
				}
				this.signStore.errors = [{ message: t('libresign', 'File not found') }]
				return
			}
			const blob = await response.blob()
			this.pdfBlob = new File([blob], 'arquivo.pdf', { type: 'application/pdf' })
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

	:deep(.notecard) {
		max-width: 600px;
		margin: 0 auto;
	}
}
</style>
