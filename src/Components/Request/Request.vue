<template>
	<div class="form-rs-container">
		<h1>{{ t('libresign', 'Request for signatures') }}</h1>
		<form @submit="e => e.preventDefault()">
			<input v-model="email" type="email" :placeholder="placeholderEmail">
			<input v-model="description" type="text" :placeholder="placeholderDescription">
			<button class="primary btn-inc" @click="addUser">
				{{ t('libresign', 'Add user') }}
			</button>
		</form>

		<div v-if="hasUsers" class="list-users-selected">
			<div id="title">
				<span>{{ t('libresign', 'Users') }}</span>
			</div>
			<ul class="list-users">
				<li v-for="values in inputValues" :key="values.email" class="list-uses-item">
					<div class="list-users-header">
						<Avatar id="avatar" :user="values.email" :display-name="values.email" />
						<p class="list-users-header-title">
							{{ values.email }}
						</p>
					</div>
					<p id="list-users-header-description">
						{{ values.description }}
					</p>
					<button id="options" @click="removeValue(values)">
						<div class="icon-close" />
					</button>
				</li>
			</ul>

			<button class="primary" @click="send">
				{{ t('libresign', 'Submit Request') }}
			</button>
		</div>
		<slot name="actions" />
	</div>
</template>

<script>
import { Avatar } from '@nextcloud/vue'

export default {
	name: 'Request',
	components: {
		Avatar,
	},
	data() {
		return {
			inputValues: [],
			idKey: 0,
			email: '',
			description: '',
		}
	},
	computed: {
		hasUsers(val) {
			return !(this.inputValues.length <= 0)
		},
		placeholderEmail() {
			return t('libresign', 'E-mail.')
		},
		placeholderDescription() {
			return t('libresign', 'Description.')
		},
	},
	methods: {
		removeValue(value) {
			this.inputValues = this.inputValues.filter(ft => {
				return ft !== value
			})
		},
		addUser() {
			this.inputValues.push({
				email: this.email,
				description: this.description,
			})
			this.email = ''
			this.description = ''
		},
		send() {
			// eslint-disable-next-line no-console
			console.log(this.inputValues)

			const file = {
				url: 'https://www.ufms.br/wp-content/uploads/2017/09/PDF-teste.pdf',
			}
			const name = 'teste'
			const users = this.inputValues

			// eslint-disable-next-line no-console
			console.log({
				file, name, users,
			})
		},
	},
}
</script>
<style lang="scss" scoped>
.form-rs-container{
	display: flex;
	flex-direction: column;

	form{
		display: flex;
		width: 100%;

		input{
			width: 40%
		}

		.btn-inc{
			width: 20%;
		}
	}

	h1{
		align-self: center;
		font-size: 1.4rem;
		font-weight: bold;
		margin-bottom: 20px;
	}

	.input-select{
		margin-bottom: 20px;
	}
	.list-users-selected{
		display: flex;
		flex-direction: column;
		align-items: center;
		width: 100%;
		border: 1px solid #cecece;
		border-radius: 10px;

		#title{
			display: flex;
			flex-direction: row;
			align-items: center;
			span{
				font-size: 1rem;
				font-weight: 400;
			}
		}

		.list-users{
			display: flex;
			flex-direction: column;
			align-items: center;
			width: 100%;
			overflow-y: scroll;
			overflow-x: hidden;
			height: 250px;

			li{
				display: flex;
				flex-direction: column;
				padding: 10px;
				border: 1px solid #cecece;
				width: 90%;
				border-radius: 10px;
				align-items: center;
				justify-content: center;
				margin: 5px;

				#avatar{
					float: left;
					left: 0;
				}
				#options{
					right: 10%;
				}
			}

			.list-users-header{
				display: inline-block;
				width: 100%;
				margin-bottom: 15px;
				.list-users-header-title{
					position: relative;
					width: 100%;
					text-align: center;
				}
			}

			.list-users-header-description{
				margin-top: 15px;
			}

			li:first-child{
				margin-top: 15px;
			}

		}
	}

}
</style>
