<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="signature-fav">
		<header>
			<h2>
				<slot name="title" />
			</h2>
			<NcActions v-if="isSignatureLoaded" :inline="2">
				<NcActionButton v-if="hasSignature" @click="removeSignature">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
				</NcActionButton>
				<NcActionButton @click="edit">
					<template #icon>
						<DrawIcon :size="20" />
					</template>
				</NcActionButton>
			</NcActions>
		</header>

		<div v-if="hasSignature">
			<PreviewSignature :src="imgSrc"
				:sign-request-uuid="signatureElementsStore.signRequestUuid"
				@loaded="signatureLoaded" />
		</div>
		<div v-else class="no-signatures" @click="edit">
			<slot name="no-signatures" />
		</div>

		<Draw v-if="isEditing"
			:draw-editor="true"
			:text-editor="true"
			:file-editor="true"
			:type="type"
			@save="save"
			@close="close" />
	</div>
</template>

<script>
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import DrawIcon from 'vue-material-design-icons/Draw.vue'

import { showError, showSuccess } from '@nextcloud/dialogs'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'

import Draw from '../../../Components/Draw/Draw.vue'
import PreviewSignature from '../../../Components/PreviewSignature/PreviewSignature.vue'

import { useSignatureElementsStore } from '../../../store/signatureElements.js'

export default {
	name: 'Signature',
	components: {
		NcActions,
		NcActionButton,
		DeleteIcon,
		DrawIcon,
		PreviewSignature,
		Draw,
	},
	props: {
		type: {
			type: String,
			required: true,
		},
	},
	setup() {
		const signatureElementsStore = useSignatureElementsStore()
		return { signatureElementsStore }
	},
	data: () => ({
		isEditing: false,
		isSignatureLoaded: false,
		signatureExists: true,
	}),
	computed: {
		hasSignature() {
			return this.signatureElementsStore.hasSignatureOfType(this.type) && this.signatureExists
		},
		imgSrc() {
			if (this.signatureElementsStore.signs[this.type]?.value?.startsWith('data:')) {
				return this.signatureElementsStore.signs[this.type].value
			}
			return `${this.signatureElementsStore.signs[this.type].file.url}&_t=${Date.now()}`
		},
	},
	methods: {
		signatureLoaded(success) {
			this.isSignatureLoaded = success
			this.signatureExists = success
		},
		edit() {
			this.isEditing = true
		},
		async removeSignature() {
			await this.signatureElementsStore.delete(this.type)
			if (this.signatureElementsStore.success.length) {
				showSuccess(this.signatureElementsStore.success)
			} else if (this.signatureElementsStore.error?.message) {
				showError(this.signatureElementsStore.error.message)
			}
		},
		close() {
			this.isEditing = false
		},
		save() {
			if (this.signatureElementsStore.success.length) {
				showSuccess(this.signatureElementsStore.success)
			} else if (this.signatureElementsStore.error?.message) {
				showError(this.signatureElementsStore.error.message)
			}
			this.close()
		},
	},
}
</script>

<style lang="scss" scoped>
.signature-fav{
	margin: 10px;

	header{
		display: flex;
		flex-direction: row;
		justify-content: space-between;

		.icon{
			cursor: pointer;
		}
	}

	img{
		max-width: 250px;
	}

	.no-signatures{
		width: 100%;
		padding: 15px;
		margin: 5px;
		border-radius: 10px;
		background-color: var(--color-main-background);
		box-shadow: 0 2px 9px var(--color-box-shadow);
		cursor: pointer;
		span{
			cursor: inherit;
		}
	}

	h2{
		width: 100%;
		padding-left: 5px;
		border-bottom: 1px solid #000;
		font-size: 1rem;
		font-weight: normal;
	}
}
</style>
