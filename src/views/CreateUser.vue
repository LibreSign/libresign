<template>
	<Content app-name="libresign">
		<div id="container">
			<form>
				<Avatar id="avatar" :user="username.length ? username : 'User'" :size="sizeAvatar" />
				<input v-model="username"
					type="text"
					required
					placeholder="Nome">
				<input type="password" required placeholder="Senha">

				<div v-tooltip.right="{
					content: 'Senha para confirmar assinatura no documento!',
					show: true,
					trigger: 'hover focus'

				}">
					<input type="password" required placeholder="Senha PFX">
				</div>
				<button>Cadastrar</button>
			</form>
		</div>
	</Content>
</template>

<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
export default {
	name: 'CreateUser',
	components: {
		Content,
		Avatar,
	},

	data() {
		return {
			username: '',
			sizeAvatar: 100,
		}
	},

	created() {
		this.changeSizeAvatar()
	},

	methods: {
		changeSizeAvatar() {
			screen.width >= 534 ? this.sizeAvatar = 150 : this.sizeAvatar = 100
		},
	},
}
</script>

<style lang="scss" scoped>
#container{
	display: flex;
	flex-direction: row;
	justify-content: center;
	align-items: center;
	width: 100%;
}

form{
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 40%
}

form > div{
	width: 100%;
}

input {
	width: 100%
}
@media screen and (max-width: 535px) {
	form {width: 90%}
}

#tooltip{
	position: relative;

	span{
		width: 160px;
		background: #fefefe;
		padding: 8px;
		border-radius: 4px;
		font-size: 14px;
		font-weight: 500;
		opacity: 0;
		transition: opacity 0.4s;
		visibility: visible;

		position: absolute;
		bottom: calc(100% + 12px);
		left: 50%;
	}
}
</style>
