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
		<Signers event="libresign:edit-signer">
			<template #actions="{signer}">
				<NcActionButton v-if="filesStore.canSave() && !signer.signed"
					aria-label="Delete"
					:close-after-click="true"
					@click="filesStore.deleteSigner(signer)">
					<template #icon>
						<Delete :size="20" />
					</template>
					{{ t('libresign', 'Delete') }}
				</NcActionButton>
				<NcActionButton v-if="filesStore.canRequestSign && !signer.signed && signer.signRequestId && !signer.me"
					icon="icon-comment"
					:close-after-click="true"
					@click="sendNotify(signer)">
					{{ t('libresign', 'Send reminder') }}
				</NcActionButton>
			</template>
		</Signers>
		<div class="action-buttons">
			<NcButton v-if="filesStore.canSave()"
				:variant="filesStore.canSign() ? 'secondary' : 'primary'"
				:disabled="hasLoading"
				@click="save()">
				<template #icon>
					<NcLoadingIcon v-if="hasLoading" :size="20" />
				</template>
				{{ labelOfSaveButton }}
			</NcButton>
			<NcButton v-if="filesStore.canSign()"
				variant="primary"
				:disabled="hasLoading"
				@click="sign()">
				<template #icon>
					<NcLoadingIcon v-if="hasLoading" :size="20" />
				</template>
				{{ t('libresign', 'Sign') }}
			</NcButton>
			<NcButton v-if="filesStore.canValidate()"
				variant="primary"
				@click="validationFile()">
				{{ t('libresign', 'Validate') }}
			</NcButton>
			<NcButton @click="openFile()">
				{{ t('libresign', 'Open file') }}
			</NcButton>
		</div>
		<VisibleElements />
		<NcModal v-if="modalSrc"
			size="full"
			:name="fileName"
			:close-button-contained="false"
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

import svgAccount from '@mdi/svg/svg/account.svg?raw'
import svgEmail from '@mdi/svg/svg/email.svg?raw'
import svgSms from '@mdi/svg/svg/message-processing.svg?raw'
import svgWhatsapp from '@mdi/svg/svg/whatsapp.svg?raw'
import svgXmpp from '@mdi/svg/svg/xmpp.svg?raw'

import Delete from 'vue-material-design-icons/Delete.vue'

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
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

export default {
	name: 'RequestSignatureTab',
	components: {
		NcActionButton,
		NcAppSidebar,
		NcAppSidebarTab,
		NcButton,
		NcIconSvgWrapper,
		NcLoadingIcon,
		NcModal,
		NcDialog,
		Delete,
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
		}
	},
	computed: {
		labelOfSaveButton() {

			if (this.filesStore.canSign()) {
				return t('libresign', 'Edit visible signatures')
			}
			if (this.isSignElementsAvailable()) {
				return t('libresign', 'Next')
			}
			return t('libresign', 'Request')
		},
		hasSigners() {
			return this.filesStore.hasSigners(this.filesStore.getFile())
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
			const config = {
				url: generateOcsUrl('/apps/libresign/api/v1/request-signature'),
				data: {
					name: this.filesStore.getFile()?.name,
					users: [],
				},
			};
			(this.filesStore.getFile()?.signers ?? []).forEach(signer => {
				const user = {
					displayName: signer.displayName,
					identify: {},
				}
				signer.identifyMethods.forEach(method => {
					user.notify = false
					if (method.method === 'account') {
						user.identify.account = method?.value?.id ?? method?.value ?? signer.uid
					} else if (method.method === 'email') {
						user.identify.email = method?.value?.id ?? method?.value ?? signer.email
					} else {
						user.identify[method.method] = method.value
					}
				})
				config.data.users.push(user)
			})
			if (this.filesStore.getFile()?.status) {
				config.data.status = this.filesStore.getFile()?.status
			} else if (!this.isSignElementsAvailable()) {
				config.data.status = 1
			} else {
				config.data.status = 0
			}

			if (this.filesStore.getFile().uuid) {
				config.data.uuid = this.filesStore.getFile().uuid
				config.method = 'patch'
			} else {
				config.data.file = {
					fileId: this.filesStore.selectedNodeId,
				}
				config.method = 'post'
			}
			await axios(config)
				.then(({ data }) => {
					this.filesStore.addFile(data.ocs.data.data)
					emit('libresign:show-visible-elements')
				})
				.catch(({ response }) => {
					if (response?.data?.ocs?.data?.message) {
						showError(response.data.ocs.data.message)
					} else if (response?.data?.ocs?.data?.errors) {
						response.data.ocs.data.errors.forEach(error => showError(error.message))
					}
				})
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

.action-buttons{
	display: flex;
	box-sizing: border-box;
	grid-gap: 10px;
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
