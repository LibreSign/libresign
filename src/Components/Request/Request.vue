<template>
	<div class="form-rs-container">
		<h1>{{ t('libresign', 'Request for signatures') }}</h1>
		<form @submit="e => e.preventDefault()">
			<input v-model="email" type="email" :placeholder="placeholderEmail">
			<input v-model="description" type="text" :placeholder="placeholderDescription">
			<button :disabled="!hasEmail" class="primary btn-inc" @click="addUser">
				{{ t('libresign', 'Add') }}
			</button>
		</form>

		<div v-if="hasUsers" class="list-users-selected">
			<div id="title">
				<span>{{ t('libresign', 'Users') }}</span>
			</div>
			<ul class="list-users">
				<li v-for="values in inputValues" :key="values.email" class="list-uses-item">
					<ListItem :user="values" :description="values.description" @remove-user="removeValue" />
				</li>
			</ul>

			<button class="primary btn" @click="send">
				{{ t('libresign', 'Submit Request') }}
			</button>
		</div>
		<slot name="actions" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import ListItem from '../ListItem'
import { validateEmail } from '../../utils/validators'
export default {
	name: 'Request',
	components: {
		ListItem,
	},
	props: {
		fileinfo: {
			type: Object,
			default: () => {},
			required: true,
		},
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
		hasEmail(val) {
			return validateEmail(this.email)
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
		async send(param) {
			const response = await axios.post(generateUrl('/apps/libresign/api/0.1/webhook/register'), {
				file: {
					fileid: this.fileinfo.id,
				},
				name: this.fileinfo.name.split('.pdf')[0],
				users: this.inputValues,
			})
			// eslint-disable-next-line no-console
			console.log(response)

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
			margin: 10px 0;

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
			max-height: 240px;

			li{
				width: 100%;
				max-height: 90px;
				display: flex;
				align-items: center;
				justify-content: center;
			}
		}

		.btn{
			width: 80%;
			margin: 12px 0;
		}
	}

}
</style>
