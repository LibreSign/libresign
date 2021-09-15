<template>
	<div class="container">
		<ul>
			<li :class="{active: isActive('text')}">
				<a href="#text"
					@click.prevent="setActive('text')">Text</a>
			</li>
			<li :class="{active: isActive('draw')}">
				<a
					href="#draw"
					@click.prevent="setActive('draw')">Draw</a>
			</li>
		</ul>

		<div class="content">
			<Editor v-show="isActive('draw')" :class="{'active show': isActive('draw')}" />
			<TextInput v-show="isActive('text')" :class="{'active show': isActive('text')}" />
		</div>
	</div>
</template>

<script>
import Editor from './Editor.vue'
import TextInput from './TextInput.vue'
export default {
	components: { TextInput, Editor },

	data: () => ({
		toolSelected: 'draw',
	}),

	methods: {
		isActive(tabItem) {
			return this.toolSelected === tabItem
		},
		setActive(tabItem) {
			this.toolSelected = tabItem
		},
	},
}
</script>
<style lang="scss" scoped>
.container{
	display: flex;
	flex-direction: column;
	width: 100%;
	height: 100%;
	margin-top: 10px;

	ul{
		display: flex;
		flex-direction: row;

		li{
			padding: 10px;

			&:hover{
				border: 1px solid #dbdbdbad;
			}
		}
	}

	.content{
		width: 100%;
		border: 1px solid #dbdbdb;
	}

	li.active{
		border: 1px solid #dbdbdb;
		border-bottom: none;
	}
}
</style>
