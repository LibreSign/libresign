<template>
	<Content class="container-account" app-name="libresign">
		<div class="content-account">
			<div class="user">
				<UserImage v-bind="{ user }" />
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
							<button v-if="!getHasPfx" @click="handleModal(true)">
								{{ t('libresign', 'Create password key') }}
							</button>
							<button v-else @click="handleModal(true)">
								{{ t('librsign', 'Reset password') }}
							</button>
						</div>
						<Modal v-if="modal"
							class="password-modal"
							size="large"
							@close="handleModal(false)">
							<CreatePassword v-if="!getHasPfx" @close="handleModal(false)" />
							<ResetPassword v-if="getHasPfx" @close="handleModal(false)" />
						</Modal>
					</div>
				</div>
			</div>

			<div class="user">
				<Signatures />
				<Documents />
			</div>
		</div>
	</Content>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import Content from '@nextcloud/vue/dist/Components/Content'
import { getCurrentUser } from '@nextcloud/auth'
import { mapGetters } from 'vuex'
import CreatePassword from '../CreatePassword.vue'
import ResetPassword from '../ResetPassword.vue'
import UserImage from './partials/UserImage.vue'
import Signatures from './partials/Signatures.vue'
import Documents from './partials/Documents.vue'

export default {
	name: 'Account',

	components: {
		Content,
		Modal,
		CreatePassword,
		ResetPassword,
		Signatures,
		UserImage,
		Documents,
	},

	data() {
		return {
			user: getCurrentUser(),
			modal: false,
		}
	},

	computed: {
		...mapGetters({
			getHasPfx: 'getHasPfx',
		}),
	},
	methods: {
		handleModal(status) {
			this.modal = status
		},
	},
}
</script>

<style lang="scss">
.password-modal .modal-container{
	width: 100%;
	max-width: 600px;
	height: 100%;
	max-height: 560px;

	@media screen and (min-width:600px) {
		width: 60%;
	}
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
			width: 50%;
			max-width: 350px;
			display: flex;
			flex-direction: column;
			align-items: flex-start;

			&:first-child {
				width: 25%;
			}

			@media screen and (max-width: 768px) {
				&, &:first-child {
					width: 50%;
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

		@media (max-width: 650px) {
			flex-direction: column;

			.user{
				width: 100%;
			}
		}
	}

}
</style>
