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
			<NcListItem class="extra" compact>
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

<script setup lang="ts">
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { computed, toRefs } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcRichText from '@nextcloud/vue/components/NcRichText'

import {
	mdiEye,
} from '@mdi/js'

import { getStatusLabel } from '../../utils/fileStatus.js'
import { openDocument } from '../../utils/viewer.js'
import SignerDetails from './SignerDetails.vue'
import type {
	LoadedValidationFileDocument,
	ValidatedChildFileRecord,
} from '../../types/index'

defineOptions({
	name: 'DocumentValidationDetails',
})

const props = withDefaults(defineProps<{
	document: LoadedValidationFileDocument | ValidatedChildFileRecord
	legalInformation?: string
	documentValidMessage?: string
	isAfterSigned?: boolean
}>(), {
	legalInformation: '',
	documentValidMessage: '',
	isAfterSigned: false,
})

const { document } = toRefs(props)

const size = computed(() => {
	if (document.value.size < 1024) return document.value.size + ' B'
	if (document.value.size < 1048576) return (document.value.size / 1024).toFixed(2) + ' KB'
	return (document.value.size / 1048576).toFixed(2) + ' MB'
})

const documentStatus = computed(() => getStatusLabel(document.value.status))

async function viewDocument() {
	if (!document.value.uuid || !document.value.name || typeof document.value.nodeId !== 'number') {
		return
	}
	const fileUrl = generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: document.value.uuid })
	await openDocument({
		fileUrl,
		filename: document.value.name,
		nodeId: document.value.nodeId,
	})
}

defineExpose({
	documentStatus,
	size,
	viewDocument,
})
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
