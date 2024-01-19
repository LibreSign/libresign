<template>
	<div class="signature-fav">
		<header>
			<h2>
				<slot name="title" />
			</h2>
			<NcActions :inline="2">
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
			<PreviewSignature :src="imgSrc" />
		</div>
		<div v-else class="no-signatures" @click="edit">
			<slot name="no-signatures" />
		</div>

		<Draw v-if="isEditing"
			:draw-editor="true"
			:text-editor="true"
			:file-editor="true"
			@save="save"
			@close="close" />
	</div>
</template>

<script>
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import DrawIcon from 'vue-material-design-icons/Draw.vue'
import PreviewSignature from '../../../Components/PreviewSignature/PreviewSignature.vue'
import { startsWith } from 'lodash-es'
import Draw from '../../../Components/Draw/Draw.vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

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
		value: {
			type: String,
			required: false,
			default: () => '',
		},
		id: {
			type: Number,
			required: false,
			default: 0,
		},
	},
	data: () => ({
		isEditing: false,
	}),
	computed: {
		hasSignature() {
			return !!this.value
		},
		imgSrc() {
			if (startsWith('data:', this.value)) {
				return this.value
			}

			return `${this.value}&_t=${Date.now()}`
		},
	},
	methods: {
		edit() {
			this.isEditing = true
		},
		async removeSignature() {
			try {
				const response = await axios.delete(
					generateOcsUrl('/apps/libresign/api/v1/account/signature/elements/{elementId}', {
						elementId: this.id,
					}),
				)
				showSuccess(response.data.message)
				this.$emit('signature:delete', {
					type: this.type,
				})
			} catch (err) {
				showError(err.response.data.message)
			}
		},
		close() {
			this.isEditing = false
		},
		async save(base64) {
			try {
				if (this.id > 0) {
					const response = await axios.patch(
						generateOcsUrl('/apps/libresign/api/v1/account/signature/elements/{elementId}', {
							elementId: this.id,
						}),
						{
							type: this.type,
							file: { base64 },
						},
					)
					showSuccess(response.data.message)
				} else {
					const response = await axios.post(
						generateOcsUrl('/apps/libresign/api/v1/account/signature/elements'),
						{
							elements: [
								{
									type: this.type,
									file: { base64 },
								},
							],
						},
					)
					showSuccess(response.data.message)
				}
			} catch (err) {
				showError(err.response.data.message)
			}
			this.$emit('save', {
				base64,
				type: this.type,
			})
			this.close()
		},
	},
}
</script>

<style lang="scss" scoped>
.signature-modal {
	background-color: red;
}

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
