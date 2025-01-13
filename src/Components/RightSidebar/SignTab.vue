<!--
- @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
-
- @author Vitor Mattos <vitor@php.rio>
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
-->

<template>
	<div class="sign-pdf-sidebar">
		<header>
			<Chip>
				{{ signStore.document.statusText }}
			</Chip>
		</header>

		<main>
			<div v-if="!signStore.mounted" class="sidebar-loading">
				<p>
					{{ t('libresign', 'Loading …') }}
				</p>
			</div>
			<div v-if="!signEnabled">
				{{ t('libresign', 'Document not available for signature.') }}
			</div>
			<Sign v-else-if="signStore.mounted"
				@signed="onSigned" />
		</main>
	</div>
</template>

<script>
import Chip from '../../Components/Chip.vue'
import Sign from '../../views/SignPDF/_partials/Sign.vue'

import { SIGN_STATUS } from '../../domains/sign/enum.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'

export default {
	name: 'SignTab',
	components: {
		Chip,
		Sign,
	},
	setup() {
		const signStore = useSignStore()
		const sidebarStore = useSidebarStore()
		return { signStore, sidebarStore }
	},
	methods: {
		signEnabled() {
			return SIGN_STATUS.ABLE_TO_SIGN === this.signStore.document.status
				|| SIGN_STATUS.PARTIAL_SIGNED === this.signStore.document.status
		},
		onSigned(data) {
			this.$router.push({
				name: this.$route.path.startsWith('/p/') ? 'ValidationFileExternal' : 'ValidationFile',
				params: {
					uuid: data.file.uuid,
					isAfterSigned: true,
				},
			})
		},
	},
}
</script>

<style lang="scss" scoped>
header {
	text-align: center;
	width: 100%;
	margin-top: 1em;
	margin-bottom: 3em;
}
main {
	flex-direction: column;
	align-items: center;
	width: 100%;
	.sidebar-loading {
		text-align: center;
	}
}
</style>
