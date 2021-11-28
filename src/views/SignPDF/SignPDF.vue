
<!--
- @copyright Copyright (c) 2021 Lyseon Tech <contato@lt.coop.br>
-
- @author Lyseon Tech <contato@lt.coop.br>
-
- @license GNU AGPL version 3 or any later version
-
- This program is free software: you can redistribute it and/or modify
- it under the terms of the GNU Affero General Public License as
- published by the Free Software Foundation, either version 3 of the
- License, or (at your option) any later version.
-
- This program is distributed in the hope that it will be useful,
- but WITHOUT ANY WARRANTY; without even the implied warranty of
- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
- GNU Affero General Public License for more details.
-
- You should have received a copy of the GNU Affero General Public License
- along with this program. If not, see <http://www.gnu.org/licenses/>.
-
-->

<template>
	<div :class="isMobile ? 'container mobile' : 'container'">
		<div v-show="viewDoc" id="viewer" class="content">
			<PDFViewer :url="pdfData.url" />
		</div>
		<Sidebar v-bind="{ document }">
			<!-- <Description
				v-if="signEnabled"
				:enable="enableToSign"
				:elements="elements"
				:user="user"
				:uuid="uuid"
				:pdf-name="pdfData.filename"
				:pdf-description="pdfData.description"
				@signed="onSigned"
				@onDocument="showDocument">
				<div v-if="needSignature && !hasSignatures">
					<button class="primary" @click="gotoAccount">
						{{ t('libresign', 'Create your signature') }}
					</button>
				</div>
			</Description> -->
			<Sign v-if="signEnabled" v-bind="{ document, uuid }" @signed="onSigned" />
			<div v-else>
				{{ t('libresign', 'Document not available for signature.') }}
			</div>
		</Sidebar>
	</div>
</template>

<script>
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import { showSuccess } from '@nextcloud/dialogs'
import { defaultsDeep, get, isEmpty } from 'lodash-es'
import { getInitialState } from '../../services/InitialStateService'
import Description from './_partials/Description'
import PDFViewer from './_partials/PDFViewer'
import { service as signerService } from '../../domains/signatures'
import { canSign, getStatusLabel, service as signService } from '../../domains/sign'
import { onError } from '../../helpers/errors'
import Sidebar from './_partials/Sidebar.vue'
import Sign from './_partials/Sign.vue'

export default {
	name: 'SignPDF',
	components: { Description, PDFViewer, Sidebar, Sign },
	mixins: [
		isMobile,
	],
	props: {
		uuid: {
			type: String,
			default: '',
		},
	},
	data() {
		const state = defaultsDeep(getInitialState() || {}, {
			action: 0,
			user: {
				name: '',
			},
			sign: {
				pdf: {
					url: '',
				},
				uuid: '',
				filename: '',
				description: null,
			},
			settings: {
				hasSignatureFile: false,
			},
		})

		return {
			state,
			viewDoc: true,
			document: {
				id: '',
				name: '',
				signers: [],
				pages: [],
				visibleElements: [],
			},
			user: {
				account: { uid: '', displayName: '' },
				settings: { canRequestSign: false, hasSignatureFile: true },
			},
			userSignatures: [],
		}
	},
	computed: {
		documentUUID() {
			return this.state?.sign?.uuid
		},
		signer() {
			return this.document.signers.find(row => row.me)
		},
		visibleElements() {
			return (this.document.visibleElements || [])
				.filter(row => row.fileUserId === this.signer.fileUserId)
		},
		pdfData() {
			const { sign } = this.state

			return {
				url: sign?.pdf?.url,
				uuid: sign?.uuid,
				filename: sign?.filename,
				description: sign?.filename,
			}
		},
		signature() {
			return this.userSignatures.find(row => {
				return row.type === 'signature'
			}) ?? {}
		},
		elements() {
			const { signature, visibleElements } = this

			const url = get(signature, ['file', 'url'])
			const id = get(signature, ['id'])

			return visibleElements.map(el => ({
				documentElementId: el.elementId,
				profileElementId: id,
				url,
			}))
		},
		hasSignatures() {
			return !isEmpty(this.userSignatures)
		},
		needSignature() {
			return !isEmpty(this.document.visibleElements)
		},
		enableToSign() {
			const { needSignature, hasSignatures } = this

			if (!needSignature) {
				return true
			}

			return hasSignatures
		},
		signEnabled() {
			return canSign(this.document.status)
		},
		status() {
			return getStatusLabel(this.document?.status)
		},
	},
	mounted() {
		// this.loadSignatures()
		this.loadDocument()
		// this.loadUser()
	},
	methods: {
		showDocument(param) {
			this.viewDoc = param
		},
		gotoAccount() {
			const url = this.$router.resolve({ name: 'Account' })

			window.location.href = url.href
		},
		onSigned(data) {
			showSuccess(data.message)
			const url = this.$router.resolve({ name: 'validationFile', params: { uuid: this.documentUUID } })
			window.location.href = url.href
		},
		async loadDocument() {
			try {
				this.document = await signService.validateByUUID(this.documentUUID)
			} catch (err) {
				onError(err)
			}
		},
		async loadUser() {
			try {
				this.user = await signerService.loadMe()
			} catch (err) {
				onError(err)
			}
		},
		async loadSignatures() {
			try {
				const { elements } = await signerService.loadSignatures()
				this.userSignatures = elements
			} catch (err) {
				onError(err)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.container {
	display: flex;
	flex-direction: row;
	width: 100%;
	height: 100%;

	.content{
		display: flex;
		flex-direction: column;
		width: 100%;
		height: 100%;
	}

	#description{
		width: 38%;

		@media (max-width: 1024px){
			width: 40%;
		}

		@media (max-width: 650px) {
			width: 100%;
			height: 20%;
		}
	}

	#viewer{
		display: flex;
		justify-content: center;
		align-items: center;
		background: #cecece;
		height: 100%;

		@media (max-width: 1024px){
			width: 60%;
		}

		@media (max-width: 650px) {
			width: 100%;
		}
	}

	@media (max-width: 650px) {
		display: flex;
		flex-direction: column;
	}

}

.mobile{
	#viewer{
		width: 100% !important;
	}
}

</style>
