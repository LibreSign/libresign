<template>
	<div class="sign-pdf-sidebar">
		<header>
			<img class="pdf-icon" :src="$options._icons.PDFIcon">
			<h1>
				{{ name }}
				<small>{{ status }}</small>
			</h1>
		</header>

		<main>
			<slot v-if="!loading" />
			<div v-else class="sidebar-loading">
				<p>
					{{ t('libresign', 'Loading') }}
				</p>
			</div>
		</main>
	</div>
</template>

<script>
import PDFIcon from '../../../assets/images/application-pdf.png'
import { getStatusLabel } from '../../../domains/sign'

export default {
	// eslint-disable-next-line vue/match-component-file-name
	name: 'SignPDFSidebar',
	_icons: {
		PDFIcon,
	},
	props: {
		document: {
			type: Object,
			required: true,
		},
		loading: {
			type: Boolean,
			required: false,
			default: false,
		},
	},
	computed: {
		status() {
			return getStatusLabel(this.document?.status)
		},
		name() {
			return this.document.name || 'unknown'
		},
	},
}
</script>

<style scoped lang="scss">
.sign-pdf-sidebar {
	min-width: 380px;
	max-width: 450px;
	height: 100%;
	display: flex;
	align-items: flex-start;
	flex-direction: column;
	margin-left: 3px;
	margin-right: 3px;
	header {
		display: block;
		text-align: center;
		width: 100%;
		margin-top: 1em;
		margin-bottom: 3em;
		h1 {
			font-size: 1.2em;
			font-weight: bold;
		}
		img {
			display: inline-block;
			margin: 0 auto;
		}
		small {
			display: block;
		}
	}
	main {
		flex-direction: column;
		align-items: center;
		width: 100%;
	}
}

.sidebar-loading {
	text-align: center;
}

.pdf-icon {
	max-height: 100px;
}
</style>
