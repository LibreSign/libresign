<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="main-view">
		<TopBar
			v-if="!isMobile"
			:sidebar-toggle="true" />
		<PdfEditor v-if="mounted && !signStore.errors.length && pdfBlobs.length > 0"
			ref="pdfEditor"
			width="100%"
			height="100%"
			:files="pdfBlobs"
			:file-names="fileNames.length > 0 ? fileNames : [pdfFileName]"
			:read-only="true"
			@pdf-editor:end-init="updateSigners" />
		<div class="button-wrapper">
			<NcButton
			v-if="isMobile"
			:wide="true"
			variant="primary"
			@click.prevent="toggleSidebar">
			{{ t('libresign', 'Sign') }}
			</NcButton>
		</div>
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
import NcButton from '@nextcloud/vue/components/NcButton'

import PdfEditor from '../../components/PdfEditor/PdfEditor.vue'
import TopBar from '../../components/TopBar/TopBar.vue'

import { loadState } from '@nextcloud/initial-state'
import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'
import { useSignMethodsStore } from '../../store/signMethods.js'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { FILE_STATUS } from '../../constants.js'

export default {
	name: 'SignPDF',
	components: {
		NcNoteCard,
		NcButton,
		TopBar,
		PdfEditor,
	},
	setup() {
		const signStore = useSignStore()
		const filesStore = useFilesStore()
		const sidebarStore = useSidebarStore()
		const signMethodsStore = useSignMethodsStore()
		const isMobile = window.innerWidth <= 512
		return { signStore, filesStore, sidebarStore, signMethodsStore, isMobile }
	},
	data() {
		return {
			mounted: false,
			pdfBlobs: [],
			fileNames: [],
		}
	},
	computed: {
		pdfFileName() {
			const doc = this.signStore.document
			const extension = doc.metadata?.extension || 'pdf'
			return `${doc.name}.${extension}`
		},
	},
	async created() {
		if (await this.redirectIfSigningInProgress()) {
			return
		}

		if (this.$route.name === 'SignPDFExternal') {
			await this.initSignExternal()
		} else if (this.$route.name === 'SignPDF') {
			await this.initSignInternal()
		} else if (this.$route.name === 'IdDocsApprove') {
			await this.initIdDocsApprove()
		}

		if (this.isMobile){
			this.toggleSidebar();
		}

		const pdfs = loadState('libresign', 'pdfs', [])
		if (pdfs.length > 0) {
			await this.handleInitialStatePdfs(pdfs)
		} else {
			await this.loadPdfsFromStore()
		}
		this.mounted = true
	},
	beforeRouteLeave(to, from, next) {
		this.sidebarStore.hideSidebar()
		next()
	},
	methods: {
		async initSignExternal() {
			await this.signStore.initFromState()
			if (!this.signStore.document.uuid) {
				this.signStore.document.uuid = this.$route.params.uuid
			}
		},
		async initSignInternal() {
			const files = await this.filesStore.getAllFiles({
				signer_uuid: this.$route.params.uuid,
			})
			for (const key in files) {
				const signer = files[key].signers.find(row => row.me) || {}
				if (Object.keys(signer).length > 0) {
					this.signStore.setFileToSign(files[key])
					this.filesStore.selectFile(parseInt(key))
					return
				}
			}
		},
		async initIdDocsApprove() {
			const response = await axios.get(
				generateOcsUrl('/apps/libresign/api/v1/file/validate/uuid/{uuid}', { uuid: this.$route.params.uuid })
			)
			this.signStore.setFileToSign(response.data.ocs.data)
			this.filesStore.selectFile(response.data.ocs.data.id)
		},
		async handleInitialStatePdfs(urls) {
			if (!Array.isArray(urls) || urls.length === 0) {
				return
			}

			const blobs = []
			for (const url of urls) {
				const response = await fetch(url)
				const contentType = response.headers.get('Content-Type') || ''

				if (contentType.includes('application/json')) {
					const data = await response.json()
					this.sidebarStore.hideSidebar()
					if (data?.errors?.[0]?.message.length > 0) {
						this.signStore.errors = data.errors
					} else {
						this.signStore.errors = [{ message: t('libresign', 'File not found') }]
					}
					return
				}

				const blob = await response.blob()
				blobs.push(new File([blob], 'arquivo.pdf', { type: 'application/pdf' }))
			}

			this.pdfBlobs = blobs
		},
		async loadPdfsFromStore() {
			const doc = this.signStore.document

			if (!doc || !doc.nodeId) {
				this.signStore.errors = [{ message: t('libresign', 'Document not found') }]
				return
			}

			if (doc.nodeType === 'envelope') {
				await this.loadEnvelopePdfs(doc.id)
			} else if (doc.url) {
				await this.handleInitialStatePdfs([doc.url])
			} else {
				this.signStore.errors = [{ message: t('libresign', 'Document URL not found') }]
			}
		},
		async loadEnvelopePdfs(parentFileId) {
			try {
				const envelopeFiles = loadState('libresign', 'envelopeFiles', [])

				let files = []
				if (envelopeFiles.length > 0) {
					files = envelopeFiles
				} else {
					const url = generateOcsUrl('/apps/libresign/api/v1/file/list')
					const params = new URLSearchParams({
						page: '1',
						length: '100',
						parentFileId: parentFileId.toString(),
						signer_uuid: this.$route.params.uuid,
					})

					const { data } = await axios.get(`${url}?${params.toString()}`)
					if (data.ocs?.data?.data) {
						files = data.ocs.data.data
					} else {
						this.signStore.errors = [{ message: t('libresign', 'Failed to load envelope files') }]
						return
					}
				}

				for (const file of files) {
					const signer = file.signers?.find(row => row.me) || {}
					if (Object.keys(signer).length > 0) {
						this.filesStore.addFile(file)
						break
					}
				}

				const urls = files.map(file => file.file)
				this.fileNames = files.map(file => {
					return `${file.name}.${file.metadata?.extension || 'pdf'}`
				})
				await this.handleInitialStatePdfs(urls)
			} catch (error) {
				this.signStore.errors = [{ message: t('libresign', 'Failed to load envelope files') }]
			}
		},
		updateSigners(data) {
			const currentSigner = this.signStore.document.signers.find(signer => signer.me)
			if (currentSigner && currentSigner.visibleElements.length > 0) {
				currentSigner.visibleElements.forEach(element => {
					const object = structuredClone(currentSigner)
					object.readOnly = true
					element.coordinates.ury = Math.round(data.measurement[element.coordinates.page].height)
						- element.coordinates.ury
					object.element = element
					this.$refs.pdfEditor.addSigner(object)
				})
			}
			this.signStore.mounted = true
		},
		toggleSidebar() {
			this.sidebarStore.toggleSidebar()
		},
		async redirectIfSigningInProgress() {
			const targetRoute = this.$route.path.startsWith('/p/') ? 'ValidationFileExternal' : 'ValidationFile'
			let targetUuid = null

			const file = this.filesStore.getFile()
			if (file && file?.status === FILE_STATUS.SIGNING_IN_PROGRESS) {
				targetUuid = file.uuid
			}

			if (!targetUuid) {
				const initialStatus = loadState('libresign', 'status', null)
				const initialUuid = loadState('libresign', 'uuid', null)
				if (initialStatus === FILE_STATUS.SIGNING_IN_PROGRESS && initialUuid) {
					targetUuid = initialUuid
				}
			}

			if (targetUuid) {
				this.$router.push({
					name: targetRoute,
					params: {
						uuid: targetUuid,
						isAfterSigned: false,
						isAsync: true,
					},
				})
				return true
			}

			return false
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
.button-wrapper {
	padding: 5px 16px;
}
</style>
