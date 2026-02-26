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
			:emit-object-click="true"
			@pdf-editor:object-click="dispatchPrimaryAction"
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
import { t } from '@nextcloud/l10n'

import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcButton from '@nextcloud/vue/components/NcButton'

import PdfEditor from '../../components/PdfEditor/PdfEditor.vue'
import TopBar from '../../components/TopBar/TopBar.vue'

import { loadState } from '@nextcloud/initial-state'
import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { FILE_STATUS } from '../../constants.js'
import {
	aggregateVisibleElementsByFiles,
	findFileById,
	getFileSigners,
	getFileUrl,
	getVisibleElementsFromDocument,
	idsMatch,
} from '../../services/visibleElementsService'

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
		const isMobile = window.innerWidth <= 512
		return { signStore, filesStore, sidebarStore, isMobile }
	},
	data() {
		return {
			mounted: false,
			pdfBlobs: [],
			fileNames: [],
			envelopeFiles: [],
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

		if (this.$route?.name === 'SignPDFExternal') {
			await this.initSignExternal()
		} else if (this.$route?.name === 'SignPDF') {
			await this.initSignInternal()
		} else if (this.$route?.name === 'IdDocsApprove') {
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
	mounted() {
		this.setupElementClickListener()
	},
	beforeUnmount() {
		this.removeElementClickListener()
	},
	beforeRouteLeave(to, from, next) {
		this.sidebarStore.hideSidebar()
		next()
	},
	methods: {
		t,
		isIdDocApproval() {
			return this.$route.query.idDocApproval === 'true'
		},
		addIdDocApprovalParam(url) {
			if (!this.isIdDocApproval() || !url) {
				return url
			}
			const separator = url.includes('?') ? '&' : '?'
			return `${url}${separator}idDocApproval=true`
		},
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
			const url = generateOcsUrl('/apps/libresign/api/v1/file/validate/uuid/{uuid}', { uuid: this.$route.params.uuid })
			const response = await axios.get(this.addIdDocApprovalParam(url))
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
			} else {
				const baseFileUrl = doc.url
					|| doc.files?.[0]?.file
					|| (doc.uuid ? generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: doc.uuid }) : null)
				const fileUrl = this.addIdDocApprovalParam(baseFileUrl)
				if (fileUrl) {
					await this.handleInitialStatePdfs([fileUrl])
				} else {
					this.signStore.errors = [{ message: t('libresign', 'Document URL not found') }]
				}
			}
		},
		async loadEnvelopePdfs(parentFileId) {
			try {
				const envelopeFiles = await this.fetchEnvelopeFiles(parentFileId)
				this.envelopeFiles = envelopeFiles
				if (this.signStore.document) {
					this.signStore.document.files = envelopeFiles
				}

				if (!envelopeFiles.length) {
					this.signStore.errors = [{ message: t('libresign', 'Failed to load envelope files') }]
					return
				}

				const fileWithMe = envelopeFiles.find(file => file.signers?.some(row => row.me))
				if (fileWithMe) {
					this.filesStore.addFile(fileWithMe)
				}

				const urls = envelopeFiles
					.map(file => getFileUrl(file))
					.filter(Boolean)
				if (!urls.length) {
					this.signStore.errors = [{ message: t('libresign', 'Failed to load envelope files') }]
					return
				}

				this.fileNames = envelopeFiles.map(file => `${file.name}.${file.metadata?.extension || 'pdf'}`)
				await this.handleInitialStatePdfs(urls)
			} catch (error) {
				this.signStore.errors = [{ message: t('libresign', 'Failed to load envelope files') }]
			}
		},
		async fetchEnvelopeFiles(parentFileId) {
			const cachedEnvelopeFiles = loadState('libresign', 'envelopeFiles', [])
			if (Array.isArray(cachedEnvelopeFiles) && cachedEnvelopeFiles.length > 0) {
				return cachedEnvelopeFiles
			}

			const url = generateOcsUrl('/apps/libresign/api/v1/file/list')
			const params = new URLSearchParams({
				page: '1',
				length: '100',
				parentFileId: parentFileId.toString(),
				signer_uuid: this.$route.params.uuid,
			})
			const finalUrl = this.addIdDocApprovalParam(`${url}?${params.toString()}`)
			const response = await axios.get(finalUrl)
			return response.data?.ocs?.data?.data ?? []
		},
		updateSigners(data) {
			if (this.signStore.document.nodeType === 'envelope' && this.envelopeFiles.length > 0) {
				const fileIndexById = new Map(
					this.envelopeFiles.map((file, index) => [String(file.id), index]),
				)
				const elements = aggregateVisibleElementsByFiles(this.envelopeFiles)
				elements.forEach(element => {
					const fileInfo = findFileById(this.envelopeFiles, element.fileId)
					const signers = getFileSigners(fileInfo)
					const signer = signers.find(row => idsMatch(row.signRequestId, element.signRequestId))
						|| signers.find(row => row.me)
					if (!signer) {
						return
					}
					const object = structuredClone(signer)
					object.readOnly = true
					object.element = {
						...element,
						documentIndex: fileIndexById.get(String(element.fileId)) ?? 0,
					}
					this.$refs.pdfEditor.addSigner(object)
				})
				this.signStore.mounted = true
				return
			}

			const currentSigner = this.signStore.document.signers.find(signer => signer.me)
			const visibleElements = getVisibleElementsFromDocument(this.signStore.document)
			const elementsForSigner = currentSigner
				? visibleElements.filter(element => idsMatch(element.signRequestId, currentSigner.signRequestId))
				: []
			if (currentSigner && elementsForSigner.length > 0) {
				elementsForSigner.forEach(element => {
					const object = structuredClone(currentSigner)
					object.readOnly = true
					object.element = element
					this.$refs.pdfEditor.addSigner(object)
				})
			}
			this.signStore.mounted = true
		},
		toggleSidebar() {
			this.sidebarStore.toggleSidebar()
		},
		setupElementClickListener() {
			this.$nextTick(() => {
				const pdfEditor = this.$refs.pdfEditor?.$el
				if (!pdfEditor) {
					return
				}

				this.elementClickHandler = this.dispatchPrimaryAction.bind(this)
				pdfEditor.addEventListener('click', this.elementClickHandler, true)
			})
		},
		removeElementClickListener() {
			if (this.elementClickHandler) {
				const pdfEditor = this.$refs.pdfEditor?.$el
				if (pdfEditor) {
					pdfEditor.removeEventListener('click', this.elementClickHandler, true)
				}
				this.elementClickHandler = null
			}
		},
		dispatchPrimaryAction(event) {
			if (!this.sidebarStore.show || this.sidebarStore.activeTab !== 'sign-tab') {
				this.sidebarStore.activeSignTab()
			}
			this.signStore.queueAction('sign')
		},
		async redirectIfSigningInProgress() {
			const targetRoute = this.$route?.path?.startsWith('/p/') ? 'ValidationFileExternal' : 'ValidationFile'
			let targetUuid = null

			const file = this.filesStore.getFile()
			if (file && file?.status === FILE_STATUS.SIGNING_IN_PROGRESS) {
				targetUuid = loadState('libresign', 'sign_request_uuid', null)
			}

			if (!targetUuid) {
				const initialStatus = loadState('libresign', 'status', null)
				if (initialStatus === FILE_STATUS.SIGNING_IN_PROGRESS) {
					targetUuid = loadState('libresign', 'sign_request_uuid', null)
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
