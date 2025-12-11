<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div id="request-signature-tab">
		<NcButton v-if="filesStore.canAddSigner()"
			:variant="hasSigners ? 'secondary' : 'primary'"
			@click="addSigner">
			{{ t('libresign', 'Add signer') }}
		</NcButton>
		<Signers event="libresign:edit-signer"
			@signing-order-changed="debouncedSave">
			<template #actions="{signer, closeActions}">
				<NcActionInput v-if="canEditSigningOrder(signer)"
					:label="t('libresign', 'Signing order')"
					type="number"
					:value="signer.signingOrder || 1"
					@update:value="updateSigningOrder(signer, $event)"
					@submit="confirmSigningOrder(signer); closeActions()"
					@blur="confirmSigningOrder(signer)">
					<template #icon>
						<OrderNumericAscending :size="20" />
					</template>
				</NcActionInput>
				<NcActionButton v-if="canDelete(signer)"
					aria-label="Delete"
					:close-after-click="true"
					@click="filesStore.deleteSigner(signer)">
					<template #icon>
						<Delete :size="20" />
					</template>
					{{ t('libresign', 'Delete') }}
				</NcActionButton>
				<NcActionButton v-if="canRequestSignature(signer)"
					:close-after-click="true"
					@click="requestSignatureForSigner(signer)">
					<template #icon>
						<Send :size="20" />
					</template>
					{{ t('libresign', 'Request signature') }}
				</NcActionButton>
				<NcActionButton v-if="canSendReminder(signer)"
					icon="icon-comment"
					:close-after-click="true"
					@click="sendNotify(signer)">
					{{ t('libresign', 'Send reminder') }}
				</NcActionButton>
			</template>
		</Signers>
		<div class="action-buttons">
			<div v-if="showSaveButton || showRequestButton" class="button-group">
				<NcButton v-if="showSaveButton"
					wide
					variant="secondary"
					:disabled="hasLoading"
					@click="save()">
					<template #icon>
						<NcLoadingIcon v-if="hasLoading" :size="20" />
						<Pencil v-else-if="isSignElementsAvailable()" :size="20" />
					</template>
					{{ isSignElementsAvailable() ? t('libresign', 'Setup signature positions') : t('libresign', 'Save') }}
				</NcButton>
				<NcButton v-if="showRequestButton"
					wide
					:variant="filesStore.canSign() ? 'secondary' : 'primary'"
					:disabled="hasLoading"
					@click="request()">
					<template #icon>
						<NcLoadingIcon v-if="hasLoading" :size="20" />
						<Send v-else :size="20" />
					</template>
					{{ t('libresign', 'Request signatures') }}
				</NcButton>
			</div>
			<div v-if="filesStore.canSign()" class="button-group">
				<NcButton wide
					variant="primary"
					:disabled="hasLoading"
					@click="sign()">
					<template #icon>
						<NcLoadingIcon v-if="hasLoading" :size="20" />
						<Draw v-else :size="20" />
					</template>
					{{ t('libresign', 'Sign document') }}
				</NcButton>
			</div>
			<div class="button-group">
				<NcButton v-if="filesStore.canValidate()"
					wide
					variant="secondary"
					@click="validationFile()">
					<template #icon>
						<Information :size="20" />
					</template>
					{{ t('libresign', 'Validation info') }}
				</NcButton>
				<NcButton wide
					variant="secondary"
					@click="openFile()">
					<template #icon>
						<FileDocument :size="20" />
					</template>
					{{ t('libresign', 'Open file') }}
				</NcButton>
			</div>
		</div>
		<VisibleElements />
		<NcModal v-if="modalSrc"
			size="full"
			:name="fileName"
			:close-button-contained="false"
			:close-button-outside="true"
			@close="closeModal()">
			<iframe :src="modalSrc" class="iframe" />
		</NcModal>
		<NcDialog v-if="filesStore.identifyingSigner"
			id="request-signature-identify-signer"
			:size="size"
			:name="t('libresign', 'Add new signer')"
			@closing="filesStore.disableIdentifySigner()">
			<NcAppSidebar :name="t('libresign', 'Add new signer')">
				<NcAppSidebarTab v-for="method in enabledMethods()"
					:id="`tab-${method.name}`"
					:key="method.name"
					:name="method.friendly_name">
					<template #icon>
						<NcIconSvgWrapper :size="20"
							:svg="getSvgIcon(method.name)" />
					</template>
					<IdentifySigner :signer-to-edit="signerToEdit"
						:placeholder="method.friendly_name"
						:method="method.name" />
				</NcAppSidebarTab>
			</NcAppSidebar>
		</NcDialog>
	</div>
</template>
<script>

import debounce from 'debounce'

import svgAccount from '@mdi/svg/svg/account.svg?raw'
import svgEmail from '@mdi/svg/svg/email.svg?raw'
import svgSms from '@mdi/svg/svg/message-processing.svg?raw'
import svgWhatsapp from '@mdi/svg/svg/whatsapp.svg?raw'
import svgXmpp from '@mdi/svg/svg/xmpp.svg?raw'

import Delete from 'vue-material-design-icons/Delete.vue'
import Draw from 'vue-material-design-icons/Draw.vue'
import FileDocument from 'vue-material-design-icons/FileDocument.vue'
import Information from 'vue-material-design-icons/Information.vue'
import OrderNumericAscending from 'vue-material-design-icons/OrderNumericAscending.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Send from 'vue-material-design-icons/Send.vue'

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'

import IdentifySigner from '../Request/IdentifySigner.vue'
import VisibleElements from '../Request/VisibleElements.vue'
import Signers from '../Signers/Signers.vue'

import svgSignal from '../../../img/logo-signal-app.svg?raw'
import svgTelegram from '../../../img/logo-telegram-app.svg?raw'
import { SIGN_STATUS } from '../../domains/sign/enum.js'
import router from '../../router/router.js'
import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'

const iconMap = {
	svgAccount,
	svgEmail,
	svgSignal,
	svgSms,
	svgTelegram,
	svgWhatsapp,
	svgXmpp,
}

import signingOrderMixin from '../../mixins/signingOrderMixin.js'

export default {
	name: 'RequestSignatureTab',
	mixins: [signingOrderMixin],
	components: {
		NcActionButton,
		NcActionInput,
		NcActions,
		NcAppSidebar,
		NcAppSidebarTab,
		NcButton,
		NcIconSvgWrapper,
		NcLoadingIcon,
		NcModal,
		NcDialog,
		Delete,
		Draw,
		FileDocument,
		Information,
		OrderNumericAscending,
		Pencil,
		Send,
		Signers,
		IdentifySigner,
		VisibleElements,
	},
	props: {
		useModal: {
			type: Boolean,
			default: false,
		},
	},
	setup() {
		const filesStore = useFilesStore()
		const signStore = useSignStore()
		const sidebarStore = useSidebarStore()
		return { filesStore, signStore, sidebarStore }
	},
	data() {
		return {
			hasLoading: false,
			signerToEdit: {},
			modalSrc: '',
			document: {},
			hasInfo: false,
			methods: [],
			signatureFlow: loadState('libresign', 'signature_flow', 'parallel'),
		}
	},
	computed: {
		isOrderedNumeric() {
			return this.signatureFlow === 'ordered_numeric'
		},
		canEditSigningOrder() {
			return (signer) => {
				return this.isOrderedNumeric
					&& this.totalSigners > 1
					&& this.filesStore.canSave()
					&& !signer.signed
			}
		},
		canDelete() {
			return (signer) => {
				return this.filesStore.canSave() && !signer.signed
			}
		},
		canRequestSignature() {
			return (signer) => {
				return this.filesStore.canRequestSign
					&& !signer.signed
					&& signer.signRequestId
					&& !signer.me
					&& signer.status === 0
			}
		},
		canSendReminder() {
			return (signer) => {
				return this.filesStore.canRequestSign
					&& !signer.signed
					&& signer.signRequestId
					&& !signer.me
					&& signer.status === 1
			}
		},
		showSaveButton() {
			if (!this.filesStore.canSave()) {
				return false
			}

			if (!this.isSignElementsAvailable()) {
				return false
			}

			const file = this.filesStore.getFile()

			if (file.status === SIGN_STATUS.PARTIAL_SIGNED || file.status === SIGN_STATUS.SIGNED) {
				return false
			}

			return true
		},
		showRequestButton() {
			if (!this.filesStore.canSave()) {
				return false
			}
			return this.hasSigners
		},
		hasSigners() {
			return this.filesStore.hasSigners(this.filesStore.getFile())
		},
		totalSigners() {
			return this.filesStore.getFile()?.signers?.length || 0
		},
		fileName() {
			return this.filesStore.getFile()?.name ?? ''
		},
		size() {
			return window.matchMedia('(max-width: 512px)').matches ? 'full' : 'normal'
		},
	},
	watch: {
		signers(signers) {
			this.init(signers)
		},
	},
	async mounted() {
		subscribe('libresign:edit-signer', this.editSigner)
		this.filesStore.disableIdentifySigner()
	},
	beforeUnmount() {
		unsubscribe('libresign:edit-signer')
	},
	created() {
		this.$set(this, 'methods', loadState('libresign', 'identify_methods'))
		this.$set(this, 'document', loadState('libresign', 'file_info', {}))

		this.debouncedSave = debounce(async () => {
			try {
				await this.filesStore.saveWithVisibleElements({ visibleElements: [] })
			} catch (error) {
				if (error.response?.data?.ocs?.data?.message) {
					showError(error.response.data.ocs.data.message)
				} else if (error.response?.data?.ocs?.data?.errors) {
					error.response.data.ocs.data.errors.forEach(error => showError(error.message))
				}
			}
		}, 1000)
	},
	methods: {
		getSvgIcon(name) {
			return iconMap[`svg${name.charAt(0).toUpperCase() + name.slice(1)}`] || iconMap.svgAccount
		},
		enabledMethods() {
			return this.methods.filter(method => method.enabled)
		},
		isSignElementsAvailable() {
			return getCapabilities()?.libresign?.config?.['sign-elements']?.['is-available'] === true
		},
		closeModal() {
			this.modalSrc = ''
			this.filesStore.flushSelectedFile()
		},
		validationFile() {
			if (this.useModal) {
				const route = router.resolve({ name: 'ValidationFileExternal', params: { uuid: this.filesStore.getFile().uuid } })
				this.modalSrc = route.href
				return
			}
			this.$router.push({ name: 'ValidationFile', params: { uuid: this.filesStore.getFile().uuid } })
			this.sidebarStore.hideSidebar()
		},
		addSigner() {
			this.signerToEdit = {}
			this.filesStore.enableIdentifySigner()
		},
		editSigner(signer) {
			this.signerToEdit = signer
			this.filesStore.enableIdentifySigner()
		},
		updateSigningOrder(signer, value) {
			const order = parseInt(value, 10)
			const file = this.filesStore.getFile()

			if (isNaN(order)) {
				return
			}

			const currentIndex = file.signers.findIndex(s => s.identify === signer.identify)
			if (currentIndex === -1) {
				return
			}

			this.$set(file.signers[currentIndex], 'signingOrder', order)

			const sortedSigners = [...file.signers].sort((a, b) => {
				const orderA = a.signingOrder || 999
				const orderB = b.signingOrder || 999
				if (orderA === orderB) {
					return 0
				}
				return orderA - orderB
			})

			this.$set(file, 'signers', sortedSigners)
		},
		confirmSigningOrder(signer) {
			const file = this.filesStore.getFile()

			const currentIndex = file.signers.findIndex(s => s.identify === signer.identify)
			if (currentIndex === -1) {
				return
			}

			const order = file.signers[currentIndex].signingOrder
			const oldOrder = signer.signingOrder

			for (let i = 0; i < file.signers.length; i++) {
				if (i === currentIndex) continue

				const currentItemOrder = file.signers[i].signingOrder

				if (order < oldOrder) {
					if (currentItemOrder >= order && currentItemOrder < oldOrder) {
						this.$set(file.signers[i], 'signingOrder', currentItemOrder + 1)
					}
				} else if (order > oldOrder) {
					if (currentItemOrder > oldOrder && currentItemOrder <= order) {
						this.$set(file.signers[i], 'signingOrder', currentItemOrder - 1)
					}
				}
			}

			const sortedSigners = [...file.signers].sort((a, b) => {
				const orderA = a.signingOrder || 999
				const orderB = b.signingOrder || 999
				return orderA - orderB
			})

			this.normalizeSigningOrders(sortedSigners)

			this.$set(file, 'signers', sortedSigners)

			this.debouncedSave()
		},
		async sendNotify(signer) {
			const body = {
				fileId: this.filesStore.selectedNodeId,
				signRequestId: signer.signRequestId,
			}

			await axios.post(generateOcsUrl('/apps/libresign/api/v1/notify/signer'), body)
				.then(({ data }) => {
					showSuccess(t('libresign', data.ocs.data.message))
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
				})

		},
		async requestSignatureForSigner(signer) {
			this.hasLoading = true
			try {
				const file = this.filesStore.getFile()
				const signers = file.signers.map(s => {
					if (s.signRequestId === signer.signRequestId) {
						return { ...s, status: 1 }
					}
					return s
				})
				await this.filesStore.updateSignatureRequest({
					visibleElements: [],
					signers,
					status: 1,
				})
				showSuccess(t('libresign', 'Signature requested'))
			} catch (error) {
				if (error.response?.data?.ocs?.data?.message) {
					showError(error.response.data.ocs.data.message)
				} else if (error.response?.data?.ocs?.data?.errors) {
					error.response.data.ocs.data.errors.forEach(error => showError(error.message))
				}
			}
			this.hasLoading = false
		},
		async sign() {
			const uuid = this.filesStore.getFile().signers
				.reduce((accumulator, signer) => {
					if (signer.me) {
						return signer.sign_uuid
					}
					return accumulator
				}, '')
			if (this.useModal) {
				const route = router.resolve({ name: 'SignPDFExternal', params: { uuid } })
				this.modalSrc = route.href
				return
			}
			this.signStore.setDocumentToSign(this.filesStore.getFile())
			this.$router.push({ name: 'SignPDF', params: { uuid } })
		},
		async save() {
			this.hasLoading = true
			try {
				await this.filesStore.saveWithVisibleElements({ visibleElements: [] })
				emit('libresign:show-visible-elements')
			} catch (error) {
				if (error.response?.data?.ocs?.data?.message) {
					showError(error.response.data.ocs.data.message)
				} else if (error.response?.data?.ocs?.data?.errors) {
					error.response.data.ocs.data.errors.forEach(error => showError(error.message))
				}
			}
			this.hasLoading = false
		},
		async request() {
			this.hasLoading = true
			try {
				const response = await this.filesStore.updateSignatureRequest({ visibleElements: [], status: 1 })
				showSuccess(t('libresign', response.message))
			} catch (error) {
				if (error.response?.data?.ocs?.data?.message) {
					showError(error.response.data.ocs.data.message)
				} else if (error.response?.data?.ocs?.data?.errors) {
					error.response.data.ocs.data.errors.forEach(error => showError(error.message))
				}
			}
			this.hasLoading = false
		},
		openFile() {
			if (OCA?.Viewer !== undefined) {
				const file = this.filesStore.getFile()
				const fileInfo = {
					source: file.file,
					basename: file.name,
					mime: 'application/pdf',
					fileid: file.nodeId,
				}
				OCA.Viewer.open({
					fileInfo,
					list: [fileInfo],
				})
			} else {
				window.open(`${this.document.file}?_t=${Date.now()}`)
			}
		},
	},
}
</script>
<style lang="scss" scoped>

.action-buttons {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-top: 12px;
}

.button-group {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.iframe {
	width: 100%;
	height: 100%;
}

#request-signature-identify-signer {
	::v-deep .app-sidebar-header{
		display: none;
	}
	::v-deep aside {
		border-left: unset;
	}
	::v-deep .app-sidebar__close {
		display: none;
	}
	@media (min-width: 513px) {
		::v-deep #app-sidebar-vue {
			width: unset;
		}
	}
}
</style>
