<template>
	<div class="form-rs-container">
		<form @submit="e => e.preventDefault()">
			<input v-model.trim="$v.email.$model"
				type="email"
				:placeholder="t('libresign', 'Email')">
			<input v-model="description" type="text" :placeholder=" t('libresign', 'Description')">
			<button :disabled="!isValid" class="primary btn-inc" @click="addUser">
				{{ t('libresign', 'Add') }}
			</button>
		</form>

		<p v-if="errorEmail">
			{{ errorEmail }}
		</p>

		<div v-if="hasUsers" class="list-users-selected">
			<div id="title">
				<span>{{ t('libresign', 'Users') }}</span>
			</div>
			<ul class="list-users">
				<li v-for="value in values" :key="value.email" class="list-uses-item">
					<ListItem :user="value" :description="value.description" @remove-user="removeValue" />
				</li>
			</ul>

			<button class="primary btn" @click.prevent="send">
				{{ t('libresign', 'Submit Request') }}
			</button>
		</div>
		<slot name="actions" />
	</div>
</template>

<script>
import ListItem from '../ListItem/index.js'
import { required, email } from 'vuelidate/lib/validators'
import { validateEmail } from '../../utils/validators.js'

export default {
	name: 'Request',
	components: {
		ListItem,
	},
	validations: {
		email: { required, email },
	},
	props: {
		fileinfo: {
			type: Object,
			default: () => {},
			required: true,
		},
		items: {
			type: Array,
			default: () => [],
			required: false,
		},
	},
	data() {
		return {
			values: [],
			email: '',
			description: '',
		}
	},
	computed: {
		hasUsers() {
			return !(this.values.length <= 0)
		},
		hasEmail() {
			return validateEmail(this.email)
		},
		hasEmailError() {
			if (this.$v.email.$dirty) {
				return this.errorEmail.length > 2
			}
			return false
		},
		errorEmail() {
			if (this.$v.email.$model && this.$v.email.$error) {
				return t('libresign', 'This is not a valid email')
			}

			return ''
		},
		isValid() {
			if (this.$v.email.$model && this.$v.email.$error) {
				return false
			}
			return true
		},
	},
	watch: {
		items(newVal) {
			this.values = newVal
		},
	},
	methods: {
		removeValue(value) {
			this.values = this.values.filter(ft => {
				return ft !== value
			})
		},
		emitDelete(value) {
			this.$emit('request:delete', value)
		},
		addUser() {
			this.values.push({
				email: this.email,
				description: this.description,
			})
			this.clearForm()
		},
		clearForm() {
			this.email = ''
			this.description = ''
		},
		async send(param) {
			this.$emit('request:signatures', this.values)
		},
		clearList() {
			this.values = []
		},
	},
}
</script>
<style lang="scss" scoped>
.form-rs-container{
	display: flex;
	flex-direction: column;
	min-height: 420px;

	form{
		display: flex;
		justify-content: space-between;
		width: 100%;
		height: 100%;
		margin-bottom: 10px;

		input{
			width: 40%
		}

		.btn-inc{
			display: flex;
			justify-content: center;
			align-items: center;
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
		max-height: calc(100vh - 360px);

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
			max-height: 300px;

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
