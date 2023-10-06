<template>
	<div id="identify-account">
		<label for="identify-search-input">{{ t('libresign', 'Search signer by account') }}</label>
		<NcSelect ref="select"
			input-id="identify-search-input"
			class="identify-search__input"
			:loading="loading"
			:filterable="false"
			:placeholder="t('libresign', 'Name')"
			:clear-search-on-blur="() => false"
			:user-select="true"
			@search="asyncFind"
			@option:selected="accountSelected">
			<template #no-options="{ search }">
				{{ search ? noResultText : t('libresign', 'No recommendations. Start typing.') }}
			</template>
		</NcSelect>
	</div>
</template>
<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

export default {
	name: 'IdentifyAccount',
	components: {
		NcButton,
		NcSelect,
	},
	data() {
		return {
			loading: false,
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
		async accountSelected(account) {
			console.log('Account selected', account)
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
