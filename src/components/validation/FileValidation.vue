<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<div class="section">
			<div class="header">
				<NcIconSvgWrapper :path="mdiInformationSlabCircle" :size="30" />
				<h1>{{ t('libresign', 'Document information') }}</h1>
			</div>
			<NcNoteCard v-if="documentValidMessage" type="success">
				{{ documentValidMessage }}
			</NcNoteCard>
			<NcNoteCard v-if="isAfterSigned" type="success">
				{{ t('libresign', 'Congratulations you have digitally signed a document using LibreSign') }}
			</NcNoteCard>
			<DocumentValidationDetails
				:document="document"
				:legalInformation="legalInformation"
			/>
		</div>
	</div>
</template>

<script>
import DocumentValidationDetails from './DocumentValidationDetails.vue'
import { t } from '@nextcloud/l10n'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { mdiInformationSlabCircle } from '@mdi/js'

export default {
	name: 'FileValidation',
	components: {
		DocumentValidationDetails,
		NcIconSvgWrapper,
		NcNoteCard,
	},
	props: {
		document: {
			type: Object,
			required: true,
		},
		legalInformation: {
			type: String,
			default: '',
		},
		documentValidMessage: {
			type: String,
			default: '',
		},
		isAfterSigned: {
			type: Boolean,
			default: false,
		},
	},
	setup() {
		return {
			t,
			mdiInformationSlabCircle,
		}
	},
}
</script>

<style lang="scss" scoped>
.section {
	background-color: var(--color-main-background);
	padding: 20px;
	border-radius: 8px;
	box-shadow: 0 0 6px 0 var(--color-box-shadow);
	margin-bottom: 16px;

	.header {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 1.5rem;

		h1 {
			font-size: 20px;
			font-weight: 600;
			margin: 0;
		}
	}

	@media screen and (max-width: 700px) {
		padding: 12px 8px;
		box-shadow: none;
		border-top: 2px solid var(--color-border-dark);
		border-radius: 0;
		margin-bottom: 0;
		margin-top: 12px;

		&:first-child {
			border-top: none;
			margin-top: 0;
		}
	}
}
</style>
