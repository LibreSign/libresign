<template>
	<div class="form-rs-container">
		<form @submit="e => e.preventDefault()">
			<input v-model="email" type="email" :placeholder="t('libresign', 'E-mail')">
			<input v-model="description" type="text" :placeholder=" t('libresign', 'Description.')">
			<button :disabled="!hasEmail" class="primary btn-inc" @click="addUser">
				{{ t('libresign', 'Add') }}
			</button>
		</form>

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
// import axios from tUrl } from '@nextcloud/router'
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
			values: [],
			email: '',
			description: '',
		}
	},
	computed: {
		hasUsers(val) {
			return !(this.values.length <= 0)
		},
		hasEmail(val) {
			return validateEmail(this.email)
		},
	},
	methods: {
		removeValue(value) {
			this.values = this.values.filter(ft => {
				return ft !== value
			})
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
			// const response = await axios.post(generateUrl('/apps/libresign/api/0.1/webhook/register'), {
			// : {
			// fileid: this.fileinfo.id,
			// },
			// name: this.fileinfo.name.split('.pdf')[0],
			// users: this.values,
			// })
			// // eslint-disable-next-line no-console
			// console.log(response)

		},
	},
}
</script>
<style lang="scss" scoped>
@import './styles';
</style>
