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
			<label for="path">{{ t('libresign', 'Signature location.') }}</label>
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
					:title="t('libresign', 'Select subscription location.')"
					:disabled="updating"
					@click.stop="pickFromCloud">
					{{ t('libresign', 'Select subscription.') }}
				</button>
			</div>
			<label for="password">{{ t('libresign', 'Subscription password.') }}</label>
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
				:value="t('libresign', 'Sign the document.')"
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
			name: t('libresign', 'Sign the document.'),
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
				this.response = t('libresign', 'Signed document available at {place}', { place: response.data.fileSigned })

			} catch (e) {
				console.error(e)
				this.error = t('libresign', 'Could not sign document!')
			}
			this.updating = false
		},

		pickFromCloud() {
			const picker = getFilePickerBuilder(t('libresign', 'Choose your subscription location!'))
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

<style scoped>

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
