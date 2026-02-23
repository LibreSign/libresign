<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="document-validation-details">
		<ul>
			<NcListItem class="extra" compact>
				<template #name>
					<strong>{{ t('libresign', 'Name:') }}</strong>
					{{ document.name }}
				</template>
			</NcListItem>
			<NcListItem v-if="document.status" class="extra" compact>
				<template #name>
					<strong>{{ t('libresign', 'Status:') }}</strong>
					{{ documentStatus }}
				</template>
			</NcListItem>
			<NcListItem v-if="document.totalPages" class="extra" compact>
				<template #name>
					<strong>{{ t('libresign', 'Total pages:') }}</strong>
					{{ document.totalPages }}
				</template>
			</NcListItem>
			<NcListItem v-if="document.size" class="extra" compact>
				<template #name>
					<strong>{{ t('libresign', 'File size:') }}</strong>
					{{ size }}
				</template>
			</NcListItem>
			<NcListItem v-if="document.pdfVersion" class="extra" compact>
				<template #name>
					<strong>{{ t('libresign', 'PDF version:') }}</strong>
					{{ document.pdfVersion }}
				</template>
			</NcListItem>
		</ul>
		<div class="info-document">
			<NcRichText v-if="legalInformation" class="legal-information" :text="legalInformation" :use-markdown="true" />
			<NcButton v-if="document.uuid" variant="primary" @click="viewDocument">
				<template #icon>
					<NcIconSvgWrapper :path="mdiEye" :size="20" />
				</template>
				{{ t('libresign', 'View document') }}
			</NcButton>
		</div>

		<ul v-if="document.signers && document.signers.length > 0" class="signers">
			<SignerDetails v-for="(signer, signerIndex) in document.signers" :key="signerIndex" :signer="signer" />
		</ul>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { n, t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcRichText from '@nextcloud/vue/components/NcRichText'

import {
	mdiEye,
	mdiInformationSlabCircle,
	mdiSignatureFreehand,
} from '@mdi/js'

import { getStatusLabel } from '../../utils/fileStatus.js'
import { openDocument } from '../../utils/viewer.js'
import SignerDetails from './SignerDetails.vue'

export default {
	name: 'DocumentValidationDetails',
	components: {
		NcButton,
		NcIconSvgWrapper,
		NcListItem,
		NcRichText,
		SignerDetails,
	},
	props: {
		document: { type: Object, required: true },
		legalInformation: { type: String, default: '' },
		documentValidMessage: { type: String, default: '' },
		isAfterSigned: { type: Boolean, default: false },
	},
	setup() {
		return { t, n, mdiEye, mdiInformationSlabCircle, mdiSignatureFreehand }
	},
	computed: {
		size() {
			if (!this.document.size) return ''
			const size = parseInt(this.document.size)
			if (size < 1024) return size + ' B'
			if (size < 1048576) return (size / 1024).toFixed(2) + ' KB'
			return (size / 1048576).toFixed(2) + ' MB'
		},
		documentStatus() {
			return getStatusLabel(this.document.status)
		},
	},
	methods: {
		async viewDocument() {
			const fileUrl = generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: this.document.uuid })
			await openDocument({
				fileUrl,
				filename: this.document.name,
				nodeId: this.document.nodeId,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.document-validation-details {
	ul {
		list-style: none;
		padding: 0;
		margin: 0;

		&.signers > li {
			margin-bottom: 12px;
		}
	}

	.info-document {
		display: flex;
		flex-direction: column;
		gap: 16px;
		margin-top: 16px;

		.legal-information {
			opacity: 0.8;
			font-size: 1rem;
		}
	}

	:deep(.list-item__wrapper) {
		margin-left: 0;
		margin-right: 0;
		border-radius: 8px;
		box-sizing: border-box;
	}
}
</style>
