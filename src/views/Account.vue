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
					<button class="primary">
						<Upload /> Upload a document
					</button>
					<button class="primary">
						<Camera /> Take a Photo
					</button>
				</div>
			</div>
			<div class="signature">
				<div class="signature-view">
					<h2>Your signature</h2>
					<hr>
					<button @click="signatureModal = true">
						Setting
					</button>
					<img :src="sign" class="signature-preview" alt="signature" />
					<Modal v-if="signatureModal" @close="signatureModal = false">
						<h1>Customize your signature.</h1>
						<label for="fullname">{{ t('libresign', 'Full name') }} : </label>
						<input id="fullname" v-model="fullname" type="text">
						<Tabs class="mt-3" :options="{ useUrlFragment: false }" @changed="tabChanged">
							<Tab
								name="Text"
								prefix="<svg xmlns='http://www.w3.org/2000/svg' width='20' height='20' fill='#4a7aab' fill-rule='nonzero'><path d='M7.3 7.9c0 .494-.4.894-.894.894s-.894-.4-.894-.894V6.707c0-.494.4-.894.894-.894h7.2c.494 0 .894.4.894.894V7.9c0 .494-.4.894-.894.894s-.894-.4-.894-.894v-.3h-1.818l.001 6.317c0 .494-.4.894-.894.894s-.894-.4-.894-.894L9.105 7.6H7.3v.308zm9.9-7.9H2.82A2.82 2.82 0 0 0 0 2.821v14.36A2.82 2.82 0 0 0 2.821 20h14.36A2.82 2.82 0 0 0 20 17.179V2.82A2.82 2.82 0 0 0 17.179 0zM2.82 1.538h14.36a1.28 1.28 0 0 1 1.282 1.282v14.36a1.28 1.28 0 0 1-1.282 1.282H2.82a1.28 1.28 0 0 1-1.282-1.282V2.82A1.28 1.28 0 0 1 2.82 1.538z'></path></svg>">
								<div class="color-picker">Color :
									<div class="color black" />
									<div class="color red" />
									<div class="color blue" />
									<div class="color green" />
								</div>
								<div class="signature-select">
									<div class="signature-radio">
										<input type="radio">
										<span class="sign-1">{{ fullname }}</span>
									</div>
								</div>
							</Tab>
							<Tab
								name="Draw"
								prefix="<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;27&quot; height=&quot;18&quot; fill=&quot;#4a7aab&quot; fill-rule=&quot;nonzero&quot;><path d=&quot;M11.082 14.408c-.583.458-1.265.89-1.75 1.125a2 2 0 0 0 .045-.29c.042-.6-.348-.848-.517-.927-.853-.4-1.774.25-2.666.88l-.165.116.094-.44c.147-.638.285-1.24-.043-1.71-.18-.256-.468-.416-.812-.45-.463-.045-1.17.08-2.894 1.36a30.54 30.54 0 0 0-2.151 1.769c-.3.273-.3.716.002.988s.788.272 1.087-.002l1.928-1.593c.69-.517 1.15-.798 1.445-.95l-.067.302c-.173.755-.39 1.695.295 2.264.248.207.69.374 1.4-.005.256-.14.537-.337.834-.546l.535-.367c-.04.332.004.692.354.947a1.27 1.27 0 0 0 .769.238c.628 0 1.33-.36 1.925-.715.718-.43 1.34-.916 1.366-.937.32-.252.357-.694.08-.986s-.763-.325-1.084-.072z&quot;></path><path d=&quot;M26.205 5.365L24.257 7.03l-2.093 5.184a.76.76 0 0 1-.475.439l-10.115 3.194c-.075.024-.152.035-.228.035a.76.76 0 0 1-.723-.986l3.194-10.113a.76.76 0 0 1 .44-.475l5.17-2.08L21.104.265a.76.76 0 0 1 .547-.265.76.76 0 0 1 .566.221l4.032 4.032a.76.76 0 0 1-.044 1.112h0zm-13.203 7.03l5.094-5.093a.76.76 0 0 1 1.072 0 .76.76 0 0 1 0 1.072l-5.093 5.093 6.817-2.153 1.832-4.54L19.7 3.753 15.155 5.58l-2.153 6.816zm8.722-10.524l-.81.95 2.735 2.734.95-.81-2.873-2.873z&quot;></path></svg>">
								<div class="color-picker">
									Color :
									<div class="color black" :class="option.penColor == '#333' && 'active'" @click="changePenColor('#333')" />
									<div class="color red" :class="option.penColor == 'red' && 'active'" @click="changePenColor('red')" />
									<div class="color blue" :class="option.penColor == 'blue' && 'active'" @click="changePenColor('blue')" />
									<div class="color green" :class="option.penColor == 'green' && 'active'" @click="changePenColor('green')" />
								</div>
								<VueSignature
									ref="signature"
									:sig-option="option"
									:w="'350px'"
									:h="'150px'" />
							</Tab>
							<Tab
								name="Upload"
								prefix="<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;17&quot; height=&quot;18&quot;><path d=&quot;M8.513 13.02a.82.82 0 0 0 .825-.815V3.358L9.27 2.13l.486.623 1.103 1.184c.14.158.34.245.54.245.4 0 .73-.29.73-.7 0-.2-.078-.368-.226-.517L9.147.28C8.94.07 8.73 0 8.513 0s-.417.07-.634.28L5.134 2.955c-.148.15-.235.307-.235.517 0 .42.33.7.72.7a.76.76 0 0 0 .556-.245L7.27 2.753l.495-.623-.07 1.227v8.846a.82.82 0 0 0 .816.815zM14.238 18C16.053 18 17 17.044 17 15.238V9.885c0-.49-.397-.886-.886-.886s-.886.397-.886.886v5.17c0 .772-.408 1.15-1.13 1.15H2.9c-.73 0-1.13-.377-1.13-1.15V9.89a.89.89 0 0 0-.89-.89.89.89 0 0 0-.89.89L0 15.238C0 17.044.964 18 2.762 18h11.476z&quot; fill=&quot;#4a7aab&quot; fill-rule=&quot;nonzero&quot;></path></svg>">
								<div class="fromImage">
									<button
										class="primary"
										v-if="!uploadSign"
										@click="$refs.signUpload.click()">
										upload signature
									</button>
									<div v-else>
										<img :src="uploadSign" alt="upload" />
										<TrashCan class="remove" @click="uploadSign = '', $refs.signUpload.value = ''" />
									</div>
								</div>
								<input
									type="file"
									@change="uploadSignature"
									ref="signUpload"
									style="display: none"
									accept="image/png, image/jpeg" />
							</Tab>
						</Tabs>
						<div class="footer">
							<button class="primary" @click="applySign">
								Apply
							</button>
							<button class="error" @click="signatureModal = false">
								Cancel
							</button>
						</div>
					</Modal>
				</div>
			</div>
		</div>
	</Content>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Content from '@nextcloud/vue/dist/Components/Content'
import { getCurrentUser } from '@nextcloud/auth'
import { mapGetters } from 'vuex'
import CreatePassword from './CreatePassword'
import ResetPassword from './ResetPassword'
import Camera from 'vue-material-design-icons/Camera'
import Upload from 'vue-material-design-icons/Upload'
import TrashCan from 'vue-material-design-icons/TrashCan'
import { Tabs, Tab } from 'vue-tabs-component/dist/index'
import 'vue-material-design-icons/styles.css'
import VueSignature from '../Components/VueSignature'

export default {
	name: 'Account',
	components: {
		Content,
		Avatar,
		Modal,
		CreatePassword,
		ResetPassword,
		Camera,
		Upload,
		TrashCan,
		Tabs,
		Tab,
		VueSignature,
	},
	data() {
		return {
			user: getCurrentUser(),
			modal: false,
			sign: '',
			signatureModal: false,
			fullname: getCurrentUser().displayName,
			uploadSign: '',
			option: {
				penColor: '#333',
				backgroundColor: '#eee',
			},
			currentTab: '',
		}
	},
	computed: {
		...mapGetters({
			hasSignature: 'getHasPfx',
		}),
	},
	methods: {
		handleModal(status) {
			this.modal = status
		},
		changePenColor(color) {
			this.option.penColor = color
		},
		uploadSignature() {
			const files = this.$refs.signUpload.files
			if (!files.length) return
			const reader = new FileReader()
			reader.onload = (e) => {
				this.uploadSign = e.target.result
			}
			reader.readAsDataURL(files[0])
		},
		tabChanged(e) {
			this.currentTab = e.tab.name
		},
		applySign() {
			if (this.currentTab === 'Draw') {
				this.sign = this.$refs.signature.save()
			} else if (this.currentTab === 'Upload') {
				this.sign = this.uploadSign
			}
			this.signatureModal = false
		}
	},
}
</script>

<style>
/* @import url('https://fonts.googleapis.com/css?family=Alex+Brush'); */
</style>

<style lang="scss">
/* @import url('https://fonts.googleapis.com/css?family=Roboto+Condensed'); */

.sig-canvas {
	width: 100%;
	height: 300px;
	background-color: rgb(200,200,200);
	/* position: fixed; */
	/* z-index: 9; */
}

.signature-preview {
	width: 100%;
}

.modal-wrapper--large .modal-container[data-v-3e0b109b]{
	width: 100%;
	height: 100%;
}

.modal-wrapper--normal .modal-container[data-v-3e0b109b]{
	width: 45%;
	padding: 20px;

	h1 {
		font-size: 1.5em;
		font-weight: bold;
		margin: 10px 0 30px;
	}

	input[type='text'] {
		width: 60%;
	}

	.mt-3 {
		margin-top: 20px;
	}

	.footer {
		text-align: right;
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

				button {
					display: flex;
					align-items: center;
					justify-content: center;

					span {
						margin-right: 10px;
					}
				}
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
		.signature {
			width: 25%;
			padding: 0 20px;
		}

	}
}

.tabs-component {
	margin: 4em 0;
}

.tabs-component-tabs {
	border: solid 1px #ddd;
	border-radius: 6px;
	margin-bottom: 5px;
}

@media (min-width: 700px) {
	.tabs-component-tabs {
		border: 0;
		align-items: stretch;
		display: flex;
		justify-content: flex-start;
		margin-bottom: -1px;
	}
}

.tabs-component-tab {
	color: #999;
	font-size: 14px;
	font-weight: 600;
	margin-right: 0;
	list-style: none;
}

.tabs-component-tab:not(:last-child) {
	border-bottom: dotted 1px #ddd;
}

.tabs-component-tab:hover {
	color: #666;
}

.tabs-component-tab.is-active {
	color: #000;
}

.tabs-component-tab.is-disabled * {
	color: #cdcdcd;
	cursor: not-allowed !important;
}

@media (min-width: 700px) {
	.tabs-component-tab {
		background-color: #fff;
		border: solid 1px #ddd;
		border-radius: 3px 3px 0 0;
		margin-right: .5em;
		transform: translateY(2px);
		transition: transform .3s ease;
	}

	.tabs-component-tab.is-active {
		border-bottom: solid 1px #fff;
		z-index: 2;
		transform: translateY(0);
	}
}

.tabs-component-tab-a {
	align-items: center;
	color: inherit;
	display: flex;
	padding: .75em 1em;
	text-decoration: none;

	svg {
		margin-right: 5px;
	}
}

.tabs-component-panels {
	padding: 1em;

	.color-picker {
		display: flex;
		align-items: center;

		div.color {
			width: 10px;
			height: 10px;
			border-radius: 50%;
			margin-left: 10px;
			padding: 1px;
			border: 2px solid transparent;
			cursor: pointer;

			&.active {
				border-color: #555;
			}

			&.black {
				background: #333;
			}

			&.red {
				background: red;
			}

			&.blue {
				background: blue;
			}

			&.green {
				background: green;
			}
		}
	}

	.signature-select {

		.signature-radio {
			display: flex;
			align-items: center;

			input[type='radio'] {
				cursor: pointer;
			}

			span {
				font-size: 25px;
				margin-left: 10px;
			}

			.sign-1{
				/* font-family: Alex Brush; */
				/* font-family: Roboto, sans-serif; */
			}
		}
	}

	.fromImage {
		width: 350px;
		height: 150px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: #eee;
		position: relative;

		img {
			position: absolute;
			top: 30%;
			left: 0;
			width: 100%;
		}

		.remove {
			position: absolute;
			right: 10px;
			top: 10px;
			cursor: pointer;
		}
	}
}

@media (min-width: 700px) {
	.tabs-component-panels {
		/* border-top-left-radius: 0; */
		background-color: #fff;
		border: solid 1px #ddd;
		border-radius: 0 6px 6px 6px;
		box-shadow: 0 0 10px rgba(0, 0, 0, .05);
		padding: 1em;
	}
}

</style>
