<template>
	<div id="identify-account">
		<label for="identify-search-input">{{ t('libresign', 'Search signer by account') }}</label>
		<NcSelect ref="select"
			v-model="selectedAccount"
			input-id="identify-search-input"
			class="identify-search__input"
			:loading="loading"
			:filterable="false"
			:placeholder="t('libresign', 'Name')"
			:user-select="true"
			:options="options"
			@search="asyncFind">
			<template #no-options="{ search }">
				{{ search ? noResultText : t('libresign', 'No recommendations. Start typing.') }}
			</template>
		</NcSelect>
	</div>
</template>
<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'IdentifyAccount',
	components: {
		NcButton,
		NcSelect,
	},
	data() {
		return {
			loading: false,
			options: [],
			selectedAccount: null,
		}
	},
	computed: {
		noResultText() {
			if (this.loading) {
				return t('libesign', 'Searching …')
			}
			return t('libesign', 'No elements found.')
		},
	},
	methods: {
		async asyncFind(search, lookup = false) {
			search = search.trim()
			this.loading = true

			let request = null
			try {
				request = await axios.get(generateOcsUrl('/apps/libresign/api/v1/identify-account/search'), {
					params: {
						search,
					},
				})
			} catch (error) {
				console.error('Error fetching suggestions', error)
				return
			}

			this.options = request.data.ocs.data
			this.loading = false
		},
	},
}
</script>

<style lang="scss" scoped>
.identify-account {
	display: flex;
	flex-direction: column;
	margin-bottom: 4px;

	label[for="sharing-search-input"] {
		margin-bottom: 2px;
	}

	&__input {
		width: 100%;
		margin: 10px 0;
	}
}
</style>
