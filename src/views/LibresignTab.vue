<template>
	<AppSidebarTab
		:id="id"
		:icon="icon"
		:name="name">
		<div v-if="error" class="emptycontent">
			<div class="icon icon-error" />
			<h2>{{ error }}</h2>
		</div>
		<div v-else-if="response" class="emptycontent">
			<div class="icon icon-checkmark" />
			<h2>{{ response }}</h2>
		</div>
		<div v-else id="libresignTabContent">
			<label for="path">{{ t('libresign', 'Local da assinatura') }}</label>
			<div class="form-group">
				<input
					id="path"
					ref="path"
					v-model="signaturePath"
					type="text"
					:disabled="1">
				<button
					id="pickFromCloud"
					:class="'icon-folder'"
					:title="t('libresign', 'Selecionar local da assinatura')"
					:disabled="updating"
					@click.stop="pickFromCloud">
					{{ t('libresign', 'Selecionar Assinatura') }}
				</button>
			</div>
			<label for="password">{{ t('libresign', 'Senha da assinatura') }}</label>
			<div class="form-group">
				<input
					id="password"
					v-model="password"
					type="password"
					:disabled="updating">
			</div>
			<input
				type="button"
				class="primary"
				:value="t('libresign', 'Assinar Documento')"
				:disabled="updating || !savePossible"
				@click="sign">
		</div>
	</AppSidebarTab>
</template>

<script>
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { joinPaths } from '@nextcloud/paths'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'LibresignTab',

	components: {
		AppSidebarTab,
	},
	mixins: [],

	props: {
		fileInfo: {
			type: Object,
			default: () => {},
			required: true,
		},
	},
	data() {
		return {
			signaturePath: '',
			password: '',
			response: '',
			icon: 'icon-rename',
			updating: false,
			loading: true,
			name: t('libresign', 'Assinar Documento'),
		}
	},

	computed: {
		id() {
			return 'libresignTab'
		},
		activeTab() {
			return this.$parent.activeTab
		},
		savePossible() {
			return (
				this.password !== ''
				&& this.signaturePath !== ''
			)
		},
	},
	methods: {
		async sign() {
			this.updating = true
			this.response = ''
			this.error = ''
			try {
				const response = await axios.post(
					generateUrl('/apps/libresign/api/0.1/sign'),
					{
						inputFilePath: joinPaths(this.fileInfo.get('path'), this.fileInfo.get('name')),
						outputFolderPath: this.fileInfo.get('path'),
						certificatePath: this.signaturePath,
						password: this.password,
					}
				)
				if (!response.data || !response.data.fileSigned) {
					throw new Error(response.data)
				}
				this.response = t('libresign', 'Documento assinado disponivel em ') + response.data.fileSigned

			} catch (e) {
				console.error(e)
				this.error = t('libresign', 'Não foi possivel assinar documento!')
			}
			this.updating = false
		},

		pickFromCloud() {
			const picker = getFilePickerBuilder(t('libresign', 'Escolha o local da assinatura'))
				.setMultiSelect(false)
				.addMimeTypeFilter('application/octet-stream')
				.setModal(true)
				.setType(1)
				.allowDirectories(false)
				.build()

			picker.pick().then((path) => {
				this.signaturePath = path
			})
		},
	},
}
</script>

<style>

#libresignTabContent {
	display: flex;
	flex-direction: column;
}

.form-group > input {
	width: 50%;
}

.form-group > input[type='button'] {
	width: 80%;
	margin: 2em;
}

#pickFromCloud{
	display: inline-block;
	background-position: 16px center;
	padding: 12px;
	padding-left: 44px;
}

</style>
