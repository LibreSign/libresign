<template>
	<Content class="container-account" app-name="libresign">
		<div class="content-account">
			<div class="user">
				<div class="user-image">
					<div class="user-image-label">
						<h1>{{ t('libresign', 'Profile picture') }}</h1>
						<div class="icons icon-contacts-dark" />
					</div>
					<Avatar :show-user-status="false"
						:size="145"
						class="user-avatar"
						:user="user.uid"
						:display-name="user.displayName" />
				</div>
				<div class="details">
					<div class="user-details">
						<h3>{{ t('libresign', 'Details') }}</h3>
						<div class="user-display-name icon-user">
							<p>{{ user.displayName }}</p>
						</div>
					</div>
					<div class="user-password">
						<h3>{{ t('libresign', 'Password & Security') }}</h3>
						<div class="user-display-password icon-password">
							<button v-if="!hasSignature" @click="handleModal(true)">
								{{ t('libresign', 'Create password key') }}
							</button>
							<button v-else @click="handleModal(true)">
								{{ t('librsign', 'Reset password') }}
							</button>
						</div>
						<Modal v-if="modal" :size="'large'" @close="handleModal(false)">
							<CreatePassword v-if="!hasSignature" @close="handleModal(false)" />
							<ResetPassword v-if="hasSignature" @close="handleModal(false)" />
						</Modal>
					</div>
				</div>
			</div>
			<div class="user sinatures">
				<h1>{{ t('libresign', 'Your signatures') }}</h1>
				<div class="signature-fav">
					<header>
						<h2>{{ t('libresign', 'Signature') }}</h2>
						<div v-if="haveSignature" class="icon icon-rename" @click="editSignatures" />
					</header>

					<img v-if="haveSignature" :src="sinatures">
					<div v-else class="no-signatures" @click="editSignatures">
						<span>
							{{ t('libresign', 'No signature, click here to create a new') }}
						</span>
					</div>
				</div>
				<div class="signature-fav">
					<header>
						<h2>{{ t('libresign', 'Initials') }}</h2>
						<div v-if="haveInitials" class="icon icon-rename" @click="editInitials" />
					</header>
					<img v-if="haveInitials" :src="sinatures">
					<div v-else class="no-signatures" @click="editInitials">
						<span>
							{{ t('libresign', 'No initials, click here to create a new') }}
						</span>
					</div>
				</div>
			</div>
			<Modal v-if="modalStatus" :size="'large'" @close="closeModal">
				<div class="container-modal-customize-signatures">
					<header>
						<h1>{{ t('libresign', 'Customize your signatures') }}</h1>
					</header>

					<div class="content">
						<Editor v-show="false" @close="closeModal" />
						<TextInput @close="closeModal" />
					</div>
				</div>
			</Modal>
		</div>
	</Content>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Content from '@nextcloud/vue/dist/Components/Content'
import { getCurrentUser } from '@nextcloud/auth'
import { mapActions, mapGetters } from 'vuex'
import CreatePassword from './CreatePassword.vue'
import ResetPassword from './ResetPassword.vue'
import Signature from '../assets/images/image.png-3.png'
import Editor from '../Components/Draw/Editor.vue'
import TextInput from '../Components/Draw/TextInput.vue'

export default {
	name: 'Account',

	components: {
		Content,
		Avatar,
		Modal,
		CreatePassword,
		ResetPassword,
		Editor,
		TextInput,
	},

	data() {
		return {
			user: getCurrentUser(),
			modal: false,
			sinatures: Signature,
		}
	},
	computed: {
		...mapGetters({
			hasSignature: 'getHasPfx',
			modalStatus: 'modal/getStatus',
			haveSignature: 'signatures/haveSignatures',
			haveInitials: 'signatures/haveInitials',
		}),
	},
	methods: {
		...mapActions({
			openModal: 'modal/OPEN_MODAL',
			closeModal: 'modal/CLOSE_MODAL',
		}),
		handleModal(status) {
			this.modal = status
		},
		editSignatures() {
			this.openModal()
		},
		editInitials() {
			this.openModal()
		},
	},
}
</script>
<style lang="scss">
.modal-wrapper--large .modal-container[data-v-3e0b109b]{
	width: 50%;
	max-width: 600px;
	height: 100%;
	max-height: 560px;
}

.container-account{
	display: flex;
	flex-direction: row;

	.content-account{
		width: 100%;
		margin: 10px;
		display: flex;
		height: 100%;

		.user{
			width: 25%;
			display: flex;
			flex-direction: column;
			align-items: center;

			.user-image {
				display: flex;
				width: 100%;
				flex-direction: column;
				align-items: center;

				h1{
					align-self: flex-start;
				}

				.user-image-label{
					display: flex;
					flex-direction: row;
					align-self: flex-start;
					margin-bottom: 20px;

					h1{
						margin-right: 10px;
					}

					.icons{
						opacity: 0.7;
					}
				}
			}

			.details{
				display: flex;
				flex-direction: column;
				width: 100%;
				padding: 10px;
				border: 0;
			}

			.user-details{
				display: flex;
				flex-direction: column;
				width: 100%;
				border: 0;

				.user-display-name[class*='icon']{
					width: 100%;
					background-position: 0px 4px;
					opacity: 0.7;
					margin-right: 10%;
					margin-bottom: 12px;
					margin-top: 12px;
					margin-left: 12px;
					padding-left: 22px;
				}
			}

			.user-password{
				display: flex;
				flex-direction: column;

				.user-display-password[class*='icon']{
					display: flex;
					background-position: 0px 10px;
					opacity: 0.7;
					margin-right: 10%;
					margin-bottom: 12px;
					margin-top: 12px;
					width: 100%;
					padding-left: 30px;
					margin-left: 15px;
					align-items: center;

					button {
						min-width: 150px;
					}
				}
			}
		}

		.sinatures {
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

		@media (max-width: 650px) {
			flex-direction: column;

			.user{
				width: 100%;
			}
		}
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
