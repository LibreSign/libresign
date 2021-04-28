<template>
	<div class="form-rs-container">
		<h1>Solicitação de assinaturas</h1>
		<form @submit="e => e.preventDefault()">
			<input v-model="email" type="email" placeholder="Email">
			<input v-model="description" type="text" placeholder="descrição">
			<button class="primary icon-add" @click="addUser">
				Incluir
			</button>
		</form>

		<div v-if="hasUsers" class="list-users-selected">
			<div id="title">
				<span>Usuarios</span>
			</div>
			<ul class="list-users">
				<li v-for="values in inputValues" :key="values.email" class="list-uses-item">
					<div class="list-users-header">
						<Avatar id="avatar" :user="values.email" :display-name="values.email" />
						<p id="list-users-header-title">
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
				Enviar solicitação
			</button>
		</div>
		<slot name="actions" />
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { Avatar } from '@nextcloud/vue'
import axios from '@nextcloud/axios'

export default {
	name: 'Request',
	components: {
		Avatar,
	},
	data() {
		return {
			inputValues: [],
			options: [],
			idKey: 0,
			email: '',
			description: '',
			hasUser: false,
		}
	},
	computed: {
		hasUsers(val) {
			return !(this.inputValues.length <= 0)
		},
	},
	created() {
		this.getUserAndGroups()
	},
	methods: {
		nItem(item) {
			this.inputValues.push(item)
			// eslint-disable-next-line no-console
			console.log(item)
			// eslint-disable-next-line no-console
			console.log(this.inputValues)
		},
		async getUserAndGroups() {
			// const groups = await axios.get(generateOcsUrl('cloud/groups?', 3))
			const users = await axios.get(generateOcsUrl('cloud/users?', 3))

			this.options = users.data.ocs.data.users
		},
		asyncFind() {
			// eslint-disable-next-line no-console
			console.log(this.inputValues)
		},
		log(param) {
			// eslint-disable-next-line no-console
			console.log(param)
		},
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
			this.hasUser = true
			this.email = ''
			this.description = ''
		},
		send() {
			// eslint-disable-next-line no-console
			console.log(this.inputValues)
		},
	},
}
</script>
<style lang="scss" scoped>
.form-rs-container{
	display: flex;
	flex-direction: column;

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
			height: 250px;

			li{
				padding: 10px;
				border: 1px solid #cecece;
				width: 90%;
				border-radius: 10px;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				margin: 5px;

				#avatar{
					left: 10%;
				}
				#options{
					right: 10%;
				}
			}

			.list-users-head{
				display: flex;
				flex-direction: row;
				margin-bottom: 15px;
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
