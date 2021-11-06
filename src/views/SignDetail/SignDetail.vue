<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import { get } from 'lodash-es'
import { service as signService } from '../../domains/sign'

export default {
	name: 'SignDetail',
	components: {
		Content,
		AppContent,
	},
	data() {
		return {
			document: {
				name: '',
				pages: [],
			},
		}
	},
	computed: {
		uuid() {
			return this.$route.params.uuid || ''
		},
		pages() {
			return get(this.document, 'pages', [])
		},
	},
	async mounted() {
		try {
			this.document = await signService.validateByUUID(this.uuid)
		} catch (err) {
			console.error(err)
		}
	},
}
</script>

<template>
	<Content app-name="libresign">
		<h2>{{ document.name }}</h2>

		<img v-for="page in pages" :key="page.url" :src="page.url">
	</Content>
</template>
