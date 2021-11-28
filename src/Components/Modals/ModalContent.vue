<template>
	<Content class="modal-content" app-name="libresign">
		<main>
			<header>
				<slot name="header">
					<h2>{{ title }}</h2>
				</slot>
			</header>
			<section>
				<slot />
			</section>
		</main>
	</Content>
</template>

<script>
import Content from '@nextcloud/vue/dist/Components/Content'
export default {
	name: 'ModalContent',
	components: {
		Content,
	},
	props: {
		title: {
			type: String,
			required: false,
			default: '',
		},
	},
}
</script>

<style lang="scss" scoped>
.modal-content {
	padding-top: 20px;
}

header, main {
	width: 100%;
}

main {
	justify-content: flex-start;
	align-items: center;
	margin: 0 1em;
	width: 100%;
	display: flex;
	flex-direction: column;
}

header {
	text-align: center;
	h2 {
		font-size: 2.5em;
	}
}
</style>
