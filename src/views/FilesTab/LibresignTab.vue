<!--
- @copyright Copyright (c) 2021 Lyseon Tech <contato@lt.coop.br>
-
- @author Lyseon Tech <contato@lt.coop.br>
- @author Vinicios Gomes <viniciusgomesvaian@gmail.com>
-
- @license GNU AGPL version 3 or any later version
-
- This program is free software: you can redistribute it and/or modify
- it under the terms of the GNU Affero General Public License as
- published by the Free Software Foundation, either version 3 of the
- License, or (at your option) any later version.
-
- This program is distributed in the hope that it will be useful,
- but WITHOUT ANY WARRANTY; without even the implied warranty of
- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
- GNU Affero General Public License for more details.
-
- You should have received a copy of the GNU Affero General Public License
- along with this program.  If not, see <http://www.gnu.org/licenses/>.
-
-->

<template>
	<AppSidebar class="app-sidebar--without-background lb-ls-root" title="LibreSign" :header="false">
		<AppSidebarTab
			id="libresign-tab"
			icon="icon-rename"
			:name="t('libresign', 'LibreSign')">
			<div v-show="showButtons" class="lb-ls-buttons">
				<button v-if="hasSign" class="primary" @click="gotoSign">
					{{ t('libresign', 'Sign') }}
				</button>
				<button
					v-if="canRequestSign"
					v-show="showRequest"
					class="primary"
					@click="option('request')">
					{{ t('libresign', 'Request') }}
				</button>
				<button v-if="haveRequest" @click="option('signatures')">
					{{ t('libresign', 'Status') }}
				</button>
				<button v-if="haveRequest" @click="gotoDetails(uuid)">
					{{ t('libresign', 'Details') }}
				</button>
				<button v-if="showValidation" @click="redirectToValidation">
					{{ t('libresign', 'Validate Document') }}
				</button>
			</div>

			<!-- <Sign v-show="signShow"
				ref="sign"
				:disabled="disabledSign"
				:pfx="hasPfx"
				:has-loading="loadingInput"
				@sign:document="signDocument">
				<template slot="actions">
					<button class="lb-ls-return-button" @click="option('sign')">
						{{ t('libresign', 'Return') }}
					</button>
				</template>
			</Sign> -->

			<Request v-show="requestShow"
				ref="request"
				:fileinfo="fileInfo"
				@request:signatures="requestSignatures">
				<template slot="actions">
					<button class="lb-ls-return-button" @click="option('request')">
						{{ t('libresign', 'Return') }}
					</button>
				</template>
			</Request>

			<div v-if="signaturesShow" id="signers" class="container-signers">
				<div class="content-signers">
					<ul>
						<li v-for="signer in signers" :key="signer.uid">
							<div class="signer-content">
								<div class="container-dot">
									<div class="icon-signer icon-user" />
									<span>
										{{ getName(signer) }}
									</span>
								</div>
								<div class="container-dot">
									<div :class="'dot ' + (signer.signed === null ? 'pending' : 'signed')" />
									<span>
										{{ signer.signed === null ? t('libresign', 'Pending') : t('libresign','Signed') }}
									</span>
								</div>
								<div v-if="showDivButtons(signer)" class="container-dot container-btn">
									<!-- <button v-if="showSignButton(signer)" class="primary" @click="changeToSign">
										{{ t('libresign', 'Sign') }}
									</button> -->
									<button v-if="showNotifyButton(signer)" class="primary" @click="resendEmail(signer.email)">
										{{ t('libresign', 'Send reminder') }}
									</button>
									<Actions v-if="showDelete(signer)">
										<ActionButton icon="icon-delete" @click="deleteUserRequest(signer)" />
									</Actions>
								</div>
							</div>
						</li>
					</ul>
					<button class="lb-ls-return-button" @click="option('signatures')">
						{{ t('libresign', 'Return') }}
					</button>
				</div>
			</div>
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import { get } from 'lodash-es'
import { service as signService, SIGN_STATUS } from '../../domains/sign'
import { getAPPURL } from '../../helpers/path'
import { showResponseError } from '../../helpers/errors'
import store from '../../store'
import Request from '../../Components/Request'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'

export default {
	name: 'LibresignTab',
	store,
	components: {
		AppSidebar,
		Actions,
		ActionButton,
		AppSidebarTab,
		Request,
	},

	data() {
		return {
			showButtons: true,
			signShow: false,
			requestShow: false,
			signaturesShow: false,
			disabledSign: false,
			signers: [],
			loadingInput: false,
			canRequestSign: false,
			haveRequest: false,
			canSign: false,
			fileInfo: null,
			showRequest: false,
			hasPfx: false,
			showValidation: false,
			uuid: '',
			settings: {
				canRequestSign: false,
				canSign: true,
				hasSignatureFile: false,
				phoneNumber: '',
				signMethod: '',
				signerFileUuid: null,
			},
		}
	},

	computed: {
		activeTab() {
			return this.$parent.activeTab
		},
		hasSignatures() {
			return !!(this.canRequestSign && this.signatures)
		},
		hasSign() {
			return !!this.canSign
		},
		signerFileUuid() {
			return get(this.se)
		},
	},

	watch: {
		fileInfo(newVal, oldVal) {
			this.getInfo()
			this.getMe()
			this.signShow = false
			this.requestShow = false
			this.signaturesShow = false

			if (newVal.name.indexOf('.signed.') !== -1 || newVal.name.indexOf('.assinado.') !== -1) {
				this.showRequest = false
				this.showValidation = true
			} else {
				this.showRequest = true
				this.showValidation = false
			}
		},

		signers() {
			if (this.signers.length <= 0) {
				this.haveRequest = false
			}
		},
	},

	methods: {
		/**
		 * Update current fileInfo and fetch new data
		 * @param {Object} fileInfo the current file FileInfo
		 */
		async update(fileInfo) {
			this.fileInfo = fileInfo
			this.resetState()
		},

		showSignButton(user) {
			if (user.me) {
				if (user.signed) {
					return false
				}
				return true
			}
		},

		showNotifyButton(user) {
			if (!user.me) {
				if (user.signed) {
					return false
				}
				return true
			}
			return false
		},
		showDelete(user) {
			if (user.signed) {
				return false
			}
			return true
		},
		showDivButtons(user) {
			return !!(this.showSignButton(user) || this.showNotifyButton(user) || this.showDelete(user))
		},
		/**
		 * Reset the current view to its default state
		 */
		resetState() {
			this.showButtons = true
			this.signShow = false
		},

		getName(user) {
			if (user.displayName) {
				return user.displayName
			} else if (user.fullName) {
				return user.fullName
			} else if (user.email) {
				return user.email
			}
			return t('libresign', 'Account not exist')
		},
		async getMe() {
			const response = await axios.get(generateUrl('/apps/libresign/api/0.1/account/me'))
			this.hasPfx = response.data.settings.hasSignatureFile
			this.canRequestSign = response.data.settings.canRequestSign
		},

		async getInfo() {
			try {
				const response = await axios.get(generateUrl(`/apps/libresign/api/0.1/file/validate/file_id/${this.fileInfo.id}`))
				this.canSign = response.data.settings.canSign
				this.uuid = response.data.uuid
				this.settings = { ...response.data.settings }

				if (response.data.signers) {
					this.haveRequest = true
					this.canRequestSign = true
					this.signers = response.data.signers
					this.signWithSMS = true
					this.phoneNumberIsRequired = true
				} else {
					this.signers = []
				}
			} catch (err) {
				this.canSign = false
				this.signers = []
			}
		},

		gotoSign(e) {
			// console.log({ x: this })
			e.preventDefault()
			const href = getAPPURL(`/p/sign/${this.settings.signerFileUuid}`)

			window.location.href = href
		},

		changeToSign() {
			this.option('signatures')
			// this.option('sign')
		},

		async signDocument(param) {
			try {
				this.loadingInput = true
				this.disabledSign = true

				const response = await axios.post(generateUrl(`/apps/libresign/api/0.1/sign/file_id/${this.fileInfo.id}`), {
					password: param,
				})

				this.getInfo()
				this.option('signatures')
				this.canSign = false
				this.loadingInput = false
				showSuccess(response.data.message)

				return OCA.Files.App.fileList.reload()
			} catch (err) {
				if (err.response.data.action === 400) {
					window.location.href = generateUrl('/apps/libresign/reset-password?redirect=CreatePassword')
				}
				this.disabledSign = false
				this.loadingInput = false
				return showResponseError(err.response)
			}
		},
		async deleteUserRequest(user) {
			const result = confirm(t('libresign', 'Are you sure you want to exclude user {email} from the request?', { email: user.email }))
			if (result === true) {
				try {
					const response = await axios.delete(generateUrl(`/apps/libresign/api/0.1/sign/file_id/${this.fileInfo.id}/${user.fileUserId}`))
					if (this.signers.length <= 0) {
						this.option('signatures')
					}

					this.getInfo()
					showSuccess(response.data.message)
				} catch (err) {
					showError(err)
				}
			}
		},

		async resendEmail(email) {
			try {
				const response = await axios.post(generateUrl('/apps/libresign/api/0.1/notify/signers'), {
					fileId: this.fileInfo.id,
					signers: [
						{
							email,
						},
					],
				})

				showSuccess(response.data.message)
			} catch (err) {
				return showResponseError(err.response)
			}
		},

		async updateRegister(users, fileInfo) {
			const response = await axios.patch(generateUrl('/apps/libresign/api/0.1/sign/register'), {
				file: {
					fileId: this.fileInfo.id,
				},
				users,
			})
			this.option('request')
			this.clearRequestList()
			await this.getInfo()

			return showSuccess(response.data.message)
		},

		async createRegister(users, fileInfo) {
			const needElements = confirm(t('libresign', 'Do you want to configure visible elements in this document?'))

			const status = needElements ? SIGN_STATUS.DRAFT : SIGN_STATUS.ABLE_TO_SIGN

			const [name] = this.fileInfo.name.split('.pdf')
			const params = {
				name,
				users,
				status,
				fileId: this.fileInfo.id,
			}

			const { message, data } = await signService.createRegister(params)

			showSuccess(message)

			if (needElements) {
				this.gotoDetails(data.uuid)
			}

			await this.$nextTick()
				.then(() => {
					this.option('request')
					this.clearRequestList()
				})
				.then(() => this.getInfo())
		},

		async requestSignatures(users, fileInfo) {
			try {
				if (this.haveRequest) {
					await this.updateRegister(users, fileInfo)
					return
				}

				await this.createRegister(users, fileInfo)

			} catch (err) {
				return showResponseError(get(err, ['response'], err))
			}
		},

		gotoDetails(uuid) {
			const href = getAPPURL(`/f/sign/${uuid}`)

			window.location.href = href
		},

		option(value) {
			if (value === 'sign') {
				// this.showButtons = !this.showButtons
				// this.signShow = !this.signShow
				console.warn('deprecated')
			} else if (value === 'request') {
				this.showButtons = !this.showButtons
				this.requestShow = !this.requestShow
			} else if (value === 'signatures') {
				this.showButtons = !this.showButtons
				this.signaturesShow = !this.signaturesShow
			}
		},
		clearSiginPassword() {
			// this.$refs.sign.clearInput()
		},
		clearRequestList() {
			this.$refs.request.clearList()
		},
		redirectToValidation() {
			window.location.href = generateUrl(`/apps/libresign/f/validation/${this.fileInfo.id}`)
		},
	},
}
</script>
<style lang="scss">
.lb-ls-root{
	width: 100% !important;
	height: calc(100vh - 223px) !important;
	min-width: 289px !important;

	.app-sidebar-header {
		display: none !important;
	}
}

.lb-ls-buttons{
	display: flex;
	flex-direction: column;
	width: 100%;

	button{
		width: 100%
	}
}

.lb-ls-return-button{
	width: 80%;
	align-self: center;
	position:absolute;
	bottom: 10px;
}

.container-signers{
	display: flex;
	width: 100%;

	.content-signers{
		width: 100%;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;

		ul {
			display: flex;
			width: 100%;
			overflow-x: scroll;
			border: 1px solid #cecece;
			border-radius: 10px;
			padding: 10px;
			height: calc(100vh - 300px);
			flex-direction: column;
			padding-bottom: 50px;

			li{
				padding: 10px;
				border: 1px solid #cecece;
				border-radius: 10px;
				display: flex;
				flex-direction: column;
				margin-bottom: 12px;

				.signer-user{
					display: flex;
					flex-direction: row;
					margin: 3px;
				}

				.container-dot{
					margin: 3px;
					display: flex;
					flex-direction: row;
					align-items: center;
					justify-content: flex-start;
					width: 100%;
					margin-bottom: 6px;
					min-height: 26px;
					cursor: inherit;

					.dot{
						width: 10px;
						height: 10px;
						border-radius: 50%;
						margin-right: 10px;
						margin-left: 3px;
						cursor: inherit;
					}

					.signed{
						background: #008000;
					}

					.pending{
						background: #d85a0b;
					}

					span{
						font-size: 14px;
						font-weight: normal;
						text-align: center;
						color: rgba(0,0,0,.7);
						cursor: inherit;
						margin-left: 5px;
					}

					button{
						min-width: 130px;
					}
				}

				.container-btn{
					display: flex;
					justify-content: space-between;
					align-items: center;
				}

			}
		}
	}
}
</style>
