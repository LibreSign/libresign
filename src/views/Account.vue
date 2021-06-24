<template>
	<Content class="container" app-name="libresign">
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
				<div class="user-details">
					<h3>{{ t('libresign', 'Details') }}</h3>
					<div class="user-display-name icon-user">
						<p>{{ user.displayName }}</p>
					</div>
				</div>
			</div>
			<div class="user-content">
				<div class="input-content">
					<div class="input-header">
						<h1>Número de telefonne</h1>
						<div class="icon icon-rename" />
					</div>
					<input type="text" placeholder="Seu número de telefone">
				</div>
				<div class="input-content">
					<div class="input-header">
						<h1>Número de telefonne</h1>
						<div class="icon icon-rename" />
					</div>
					<input type="text" placeholder="Seu número de telefone">
				</div>
				<div class="input-content">
					<div class="input-header">
						<h1>Número de telefonne</h1>
						<div class="icon icon-rename" />
					</div>
					<input type="text" placeholder="Seu número de telefone">
				</div>
				<div class="input-content">
					<div class="input-header">
						<h1>Número de telefonne</h1>
						<div class="icon icon-rename" />
					</div>
					<input type="text" placeholder="Seu número de telefone">
				</div>
				<div class="input-content">
					<div class="input-header">
						<h1>Número de telefonne</h1>
						<div class="icon icon-rename" />
					</div>
					<input type="text" placeholder="Seu número de telefone">
				</div>
			</div>
		</div>
	</Content>
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import axios from '@nextcloud/axios'
import Content from '@nextcloud/vue/dist/Components/Content'
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'

export default {
	name: 'Account',
	components: {
		Content,
		Avatar,
	},
	data() {
		return {
			user: getCurrentUser(),
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
							name: 'filename',
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
	methods: {
		async getTypes() {
			try {
				const response = await axios.get(generateUrl('/apps/libresign/api/0.1/'))
				this.documentTypes = response.data.document_types
			} catch (err) {
				showError(err.response.errors)
			}
		},
		getFile(type) {
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
							this.sendFile(file, type)
						}
					})
				})
		},
		async sendFile(file, type) {
			try {
				const response = await axios.post(generateUrl('/apps/libresign/api/0.1/file/sd'), {
					type,
					file: {
						fileId: file.id,
					},
				})
				console.info(response)
			} catch (err) {
				showError(err.response.message)
			}
		},
	},
}
</script>
<style lang="scss" scoped>
.container{
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

			.input-header {
				display: flex;

				h1{
					padding: 0 15px 5px 0;
				}
			}

			input{
				width: 90%;
				max-width: 345px;
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

			.user-details{
				display: flex;
				flex-direction: column;
				width: 100%;
				padding: 10px 10px;
				border: 0;

				.user-display-name[class*='icon']{
					background-position: 0px 2px;
					padding-left: 30px;
					opacity: 0.7;
					margin-right: 10%;
					margin-bottom: 12px;
					margin-top: 12px;
				}

			}
		}

	}
}
</style>
