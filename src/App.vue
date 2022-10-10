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
	<NcContent app-name="libresign">
		<NcAppNavigation>
			<template #list>
				<NcAppNavigationItem v-if="back_to_signature"
					class="back_to_signature"
					icon="icon-history"
					:title="t('libresign', 'Back to sign')"
					exact
					@click="goToSign" />
				<NcAppNavigationItem :to="{name: 'requestFiles'}"
					:title="t('libresign', 'Request')"
					icon="icon-rename"
					exact />
				<NcAppNavigationItem :to="{ name: 'signFiles' }"
					:title="t('libresign', 'Files')"
					icon="icon-files-dark"
					exact />
				<NcAppNavigationItem :to="{name: 'validation'}"
					:title="t('libresign', 'Validate')"
					icon="icon-file" />

				<NcAppNavigationItem v-if="settings.isApprover"
					:to="{name: 'DocsAccountValidation'}"
					:title="t('libresign', 'Documents Validation')"
					icon="icon-user" />
			</template>
			<template #footer>
				<NcAppNavigationSettings :title="t('libresign', 'Settings')">
					<CroppedLayoutSettings />
				</NcAppNavigationSettings>
			</template>
		</NcAppNavigation>
		<NcAppContent :class="{'icon-loading' : loading }">
			<router-view v-if="!loading" :key="$route.name " :loading.sync="loading" />
			<NcEmptyContent v-if="isRoot" class="emp-content">
				<template #icon>
					<img :src="icon">
				</template>
				<template #desc>
					<p>
						{{ t('libresign', 'LibreSign, digital signature app for Nextcloud.') }}
					</p>
				</template>
			</NcEmptyContent>
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem'
import NcAppNavigationSettings from '@nextcloud/vue/dist/Components/NcAppNavigationSettings'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent'
import Icon from './assets/images/signed-icon.svg'
import CroppedLayoutSettings from './Components/Settings/CroppedLayoutSettings.vue'
import { getInitialState } from './services/InitialStateService.js'
import { defaults } from 'lodash-es'

const libresignState = getInitialState()

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationSettings,
		NcAppContent,
		NcEmptyContent,
		CroppedLayoutSettings,
	},
	data() {
		return {
			settings: defaults({}, libresignState?.settings || {}, {
				hasSignatureFile: false,
				isApprover: false,
				phoneNumber: '',
				signMethod: 'password',
			}),
			loading: false,
			icon: Icon,
		}
	},
	computed: {
		back_to_signature() {
			return this.$route.query._back_to_signature
		},
		isRoot() {
			return this.$route.path === '/'
		},
	},
	methods: {
		goToSign() {
			const route = this.$router.resolve({ name: 'SignPDF', params: { uuid: this.back_to_signature } })

			window.location = route.href
		},
	},
}
</script>

<style lang="scss" scoped>
.emp-content{
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	height: 70%;

	margin-top: 10vh;
	p{
		opacity: .6;
	}

	img{
		width: 400px;
	}
}
</style>

<style>
.back_to_signature .app-navigation-entry__title {
	color: var(--color-warning, #eca700);
}
</style>
