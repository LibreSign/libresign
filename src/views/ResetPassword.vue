<template>
	<Content app-name="libresign">
		<form @submit="e => preventDefault()">
			<header>
				<h1>{{ t('libresign', 'Password reset') }}</h1>
				<p>{{ t('libresign', 'Enter new password and then repeat it') }}</p>
			</header>
			<div class="container">
				<div class="input-group">
					<label for="new-password">{{ t('libresign', 'New password') }}</label>
					<div class="input-item">
						<label for="new-password" class="icon-category-security" />
						<input id="new-password" v-model="password" type="password">
					</div>
				</div>
				<div class="input-group">
					<label for="repeat-password">{{ t('libresign', 'Repeat password') }}</label>
					<div class="input-item">
						<label for="repeat-password" class="icon-category-security" />
						<input id="repeat-password" v-model="rPassword" type="password">
					</div>
				</div>
				<button class="primary btn-confirm" @click="send">
					{{ t('libresign', 'Confirm') }}
				</button>
			</div>
		</form>
	</Content>
</template>

<script>
import Content from '@nextcloud/vue/dist/Components/Content'
export default {
	name: 'ResetPassword',
	components: {
		Content,
	},
	data() {
		return {
			password: '',
			rPassword: '',
		}
	},
	computed: {
		hasEqualPassword(val) {
			return this.password === this.rPassword
		},
	},
}
</script>
<style lang="scss" scoped>
	form{
		display: flex;
		flex-direction: column;
		width: 100%;
		max-width: 100%;
		justify-content: center;
		align-items: center;
		text-align: center;
		header{
			margin-bottom: 2.5rem;
		}
		h1{
			font-size: 45px;
			margin-bottom: 1rem;
		}
		p{
			font-size: 15px;
		}
	}

	.container {
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;

		width: 100%;
		max-width: 420px;

		text-align: start;
	}

	.btn-confirm{
		width: 100%;
		max-width: 280px;
	}

	.input-item{
		display: flex;
		flex-direction: row;
		border: 1px solid #cecece;
		border-radius: 10px;
		width: 100%;
		max-width: 380px;
		label{
			padding: 0 20px;
			border-radius: 10px 0 0 10px;
			background-color: #cecece;
		}
		input{
			border: none;
		}
		&:focus-within{
			border: thin solid #0082c9;
			box-shadow: inset 0 0 1em rgba(0, 130, 201, .5);
		}
	}

	.input-group{
		display: flex;
		flex-direction: column;
		margin: 10px;
		width: 100%;
		label:first-child{
			opacity: 0.7;
		}
		input{
			width: 100%;
			max-width: 370px;
		}
	}
</style>
