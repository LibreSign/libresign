<script>
import Draw from '../../../Components/Draw'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import { startsWith } from 'lodash-es'

export default {
	name: 'Signature',
	components: {
		Draw,
		Modal,
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

<template>
	<div class="signature-fav">
		<header>
			<h2>
				<slot name="title" />
			</h2>
			<div v-if="hasSignature" class="icon icon-rename" @click="edit" />
		</header>

		<img v-if="hasSignature" :src="imgSrc">
		<div v-else class="no-signatures" @click="edit">
			<slot name="no-signatures" />
		</div>

		<Modal v-if="isEditing" size="large --scroll" v-on="{ close }">
			<div class="container-modal-customize-signatures">
				<header>
					<h1>{{ t('libresign', 'Customize your signatures') }}</h1>
				</header>

				<div class="content">
					<Draw text-editor file-editor v-on="{ close, save }" />
				</div>
			</div>
		</Modal>
	</div>
</template>

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
		background-color: #cecece;
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

.container-modal-customize-signatures{
	display: flex;
	flex-direction: column;
	align-items: center;
	width: calc(100% - 20px);
	height: calc(100% - 40px);
	margin: 20px;

	header{
		width: 100%;

		h1{
			border-bottom: 2px solid #000;
			width: 95%;
			font-size: 1.5rem;
			padding-bottom: 5px;
			padding-left: 10px;
		}
	}

	.content{
		display: flex;
		flex-direction: column;
		width: 100%;
		height: 100%;
	}
}
</style>
