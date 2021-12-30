<template>
	<div class="documents-container">
		<h1>{{ t("libresign", "Your profile documents") }}</h1>

		<div class="contents">
			<Document v-for="(item, i) in documents"
				:key="i"
				:item="item"
				@save="loadFiles"
				@delete="loadFiles" />
		</div>
	</div>
</template>

<script>
import { mapActions, mapGetters } from 'vuex'
import Document from './Document'
export default {
	name: 'Documents',
	components: {
		Document,
	},
	computed: {
		...mapGetters({ documents: 'documents/documents' }),
	},
	mounted() {
		this.loadFiles()
	},
	methods: {
		...mapActions({ listFiles: 'documents/list' }),
		loadFiles() {
			this.listFiles()
		},
	},
}
</script>

<style lang="scss" scoped>
.documents-container {
	.contents {
		padding: 10px;
	}

	h1 {
		font-size: 1.3rem;
		font-weight: bold;
		border-bottom: 1px solid #000;
		padding-left: 5px;
		width: 100%;
	}
}
</style>
