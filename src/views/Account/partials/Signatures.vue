<script>
import { mapActions, mapGetters, mapState } from 'vuex'
import Draw from '../../../Components/Draw'
import Modal from '@nextcloud/vue/dist/Components/Modal'

export default {
	name: 'Signatures',
	components: {
		Draw,
		Modal,
	},
	data() {
		return {
			type: '',
		}
	},
	computed: {
		...mapState({
			signature: state => state.signatures.signatures.file.base64,
			initials: state => state.signatures.initials.file.base64,
		}),
		...mapGetters({
			hasSignature: 'getHasPfx',
			modalStatus: 'modal/getStatus',
			haveSignature: 'signatures/haveSignatures',
			haveInitials: 'signatures/haveInitials',
			enabledFeatures: 'featureController/getEnabledFeatures',
		}),
		isModalOpen() {
			return this.modalStatus
		},
	},
	created() {
		this.fetchSignatures()
	},
	methods: {
		...mapActions({
			openModal: 'modal/OPEN_MODAL',
			closeModal: 'modal/CLOSE_MODAL',
			fetchSignatures: 'signatures/FETCH_SIGNATURES',
			newSignature: 'signatures/NEW_SIGNATURE',
		}),
		editSignatures(type) {
			this.type = type
			this.openModal()
		},
		addNewSignature(param) {
			this.newSignature({
				type: this.type,
				file: {
					base64: param,
				},
			})
		},
	},
}
</script>

<template>
	<div class="signatures">
		<Modal v-if="isModalOpen" size="large" @close="closeModal">
			<div class="container-modal-customize-signatures">
				<header>
					<h1>{{ t('libresign', 'Customize your signatures') }}</h1>
				</header>

				<div class="content">
					<Draw @save="addNewSignature" @close="closeModal" />
				</div>
			</div>
		</Modal>

		<h1>{{ t('libresign', 'Your signatures') }}</h1>

		<!-- Signature -->
		<div class="signature-fav">
			<header>
				<h2>{{ t('libresign', 'Signature') }}</h2>
				<div v-if="haveSignature" class="icon icon-rename" @click="editSignatures('signature')" />
			</header>

			<img v-if="haveSignature" :src="signature">
			<div v-else class="no-signatures" @click="editSignatures('signature')">
				<span>
					{{ t('libresign', 'No signature, click here to create a new') }}
				</span>
			</div>
		</div>

		<!-- Initials -->
		<div class="signature-fav">
			<header>
				<h2>{{ t('libresign', 'Initials') }}</h2>
				<div v-if="haveInitials" class="icon icon-rename" @click="editSignatures('initials')" />
			</header>

			<img v-if="haveInitials" :src="initials">

			<div v-else class="no-signatures" @click="editSignatures('initials')">
				<span>
					{{ t('libresign', 'No initials, click here to create a new') }}
				</span>
			</div>
		</div>
	</div>
</template>

<style lang="scss" scoped>
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

.signatures {
	align-items: flex-start;

	h1{
		font-size: 1.3rem;
		font-weight: bold;
		border-bottom: 1px solid #000;
		padding-left: 5px;
		width: 100%;
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
}
</style>
