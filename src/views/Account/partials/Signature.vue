<template>
	<div class="signature-fav">
		<header>
			<h2>
				<slot name="title" />
			</h2>
			<div v-if="hasSignature" class="icon icon-rename" @click="edit" />
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
import PreviewSignature from '../../../Components/PreviewSignature/PreviewSignature.vue'
import { startsWith } from 'lodash-es'
import Draw from '../../../Components/Draw/Draw.vue'

export default {
	name: 'Signature',
	components: {
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
		close() {
			this.isEditing = false
		},
		save(value) {
			this.$emit('save', {
				base64: value,
				type: this.type,
			})

			this.$nextTick(() => {
				this.close()
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.signature-modal {
	background-color: red;
}

.signature-fav{
	width: 90%;
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
		padding-left: 5px;
		border-bottom: 1px solid #000;
		width: 50%;
		font-size: 1rem;
		font-weight: normal;
	}
}
</style>
