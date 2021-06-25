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
							<CreatePassword v-if="!hasSignature" />
							<ResetPassword v-if="hasSignature" />
						</Modal>
					</div>
				</div>
			</div>
			<div class="user-content">
				<div v-for="profileType in orderFiles" :key="profileType.code" class="input-content">
					<div class="input-header" @click="getFile(profileType.code)">
						<h1>{{ profileType.description }}</h1>
						<div v-tooltip.right="{
								content: t('libresign', 'Click here to select your document'),
								show: true,
								autohide: true,
								trigger: 'hover focus'
							}"
							class="icons icon-file" />
					</div>
					<div class="input-path">
						<input type="text" disabled :placeholder="profileType.libresignFile ? profileType.libresignFile.name : profileType.description ">
					</div>
				</div>
			</div>
		</div>
	</Content>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import axios from '@nextcloud/axios'
import Content from '@nextcloud/vue/dist/Components/Content'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import CreatePassword from './CreatePassword.vue'
import ResetPassword from './ResetPassword.vue'

export default {
	name: 'Account',
	components: {
		Content,
		Avatar,
		Modal,
		CreatePassword,
		ResetPassword,
	},
	data() {
		return {
			user: getCurrentUser(),
			modal: false,
			account: {
				displayName: 'Iara',
				email: 'Iara@test.coop',
				notifications: {},
				profileFileTypes: [
					{
						code: 'RG',
						name: 'RG',
						description: 'Registro Geral',
						libresignFile: null,
					},
					{
						code: 'CPF',
						name: 'CPF',
						description: 'Cadastro de Pessoa Física',
						libresignFile: {
							uuid: '3fa85f64-5717-4562-b3fc-2c963f66afa6',
							name: 'filename.pdf',
							callback: 'http://app.test.coop/callback_webhook',
							status: 'signed',
							status_date: '2021-12-31 22:45:50',
							request_date: '2021-12-31 22:45:50',
							requested_by: {
								display_name: 'John Doe',
								uid: 'johndoe',
							},
							file: {
								typeCode: 'RG',
								mimetype: 'application/pdf',
								extension: 'pdf',
								url: 'http://cloud.test.coop/apps/libresign/pdf/46d30465-ae11-484b-aad5-327249a1e8ef',
								nodeId: 2312,
							},
							signers: [
								{
									email: 'user@test.coop',
									me: true,
									display_name: 'John Doe',
									uid: 'johndoe',
									description: "As the company's CEO, you must sign this contract",
									sign_date: '2021-12-31 22:45:50',
									request_sign_date: '2021-12-31 22:45:50',
								},
							],
						},
					},
				],
			},
		}
	},
	computed: {
		orderFiles() {
			return this.account.profileFileTypes.slice().sort((a, b) => (a.code > b.code) ? 1 : -1)
		},
		...mapGetters({
			hasSignature: 'getHasPfx',
		}),
	},
	methods: {
		async getTypes() {
			try {
				const response = await axios.get(generateUrl('/apps/libresign/api/0.1/'))
				this.documentTypes = response.data.document_types
			} catch (err) {
				showError(err.response.errors)
			}
		},
		getFile(code) {
			const picker = getFilePickerBuilder(t('libresign', 'Select your file'))
				.setMultiSelect(false)
				.setMimeTypeFilter('application/pdf')
				.setModal(false)
				.setType(1)
				.allowDirectories(false)
				.build()

			picker.pick()
				.then(path => {
					OC.dialogs.filelist.forEach(file => {
						if (file.name === path.split('/')[1]) {
							this.sendFile(file, code)
						}
					})
				})
		},
		sendFile(file, code) {
			const newVal = this.account.profileFileTypes.filter(fileMap => fileMap.code !== code)
			const oldVal = this.account.profileFileTypes.filter(fileMap => fileMap.code === code)[0]
			oldVal.libresignFile = file
			newVal.push(oldVal)
			this.account.profileFileTypes = newVal
		},
		handleModal(status) {
			this.modal = status
		},
	},
}
</script>
<style lang="scss">
.modal-wrapper--large .modal-container[data-v-3e0b109b]{
	width: 100%;
	height: 100%;
}
.container-account{
	display: flex;
	flex-direction: row;

	.content-account{
		width: 100%;
		margin: 10px;
		display: flex;
		height: 100%;

		.user-content{
			display: flex;
			width: 75%;
			flex-direction: row;
			flex-wrap: wrap;
			margin-right: 15px;
			height: 100%;
		}

		.input-content {
			display: flex;
			width: 33%;
			flex-direction: column;
			height: 145px;

			.input-path{
				width: 100%;

				input{
					width: 75%;
					max-width: 345px;
				}

				.button{
					width: 40px;
					height: 30px;
				}
			}

			.input-header {
				display: flex;
				cursor: pointer;

				h1{
					padding: 0 15px 5px 0;
				}
			}

		}

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

	}
}
</style>
