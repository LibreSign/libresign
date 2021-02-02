<template>
	<div class="container">
		<header>
			<img :src="image">
			<p>{{ pdfName }}</p>
			<span>{{ pdfDescription }}</span>
		</header>
		<div id="body">
			<form @submit="e => e.preventDefault()">
				<div v-show="signaturePath" class="form-group">
					<label for="password">Senha da Assinatura</label>
					<div class="form-ib-group">
						<input id="password" v-model="password" type="password">
						<button type="button"
							:value="'Assinar Documento'"
							class="primary"
							:disabled="updating"
							@click="checkAssign">
							Assinar Documento
						</button>
					</div>
				</div>
			</form>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import Image from '../assets/images/application-pdf.png'
import { generateUrl } from '@nextcloud/router'
import { joinPaths } from '@nextcloud/paths'

export default {
	name: 'Description',

	props: {
		pdfName: {
			type: String,
			required: true,
			default: 'Nome do PDF',
		},
		pdfDescription: {
			type: String,
			required: false,
			default: 'Descrição',
		},
		pdfFile: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			image: Image,
			updating: false,
			signaturePath: '2',
			password: '',
			asign: true,
		}
	},

	computed: {
		hasSavePossible() {
			return !!(this.password.lenght > 0)
		},
	},

	methods: {

		async sign() {
			this.updating = true
			try {
				const response = await axios.post(generateUrl('/apps/libresign/api/0.1/sign'), {
					inputFilePath: joinPaths(
						this.fileInfo.get('path'), this.fileInfo.get('name')
					),
					outputFolderPath: this.fileInfo.get('path'),
					certificatePath: this.signaturePath,
					password: this.password,
				})
				showSuccess(response)
			} catch (err) {
				showError(err)
			}
		},

		checkAssign() {
			if (this.hasSavePossible === true) {
				showSuccess('Assinado!')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.container{
	display: flex;
	flex-direction: column;
	align-items: center;

	width: 100%;
	height: 100%;
}

header{
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
	padding-top: 50px;
	padding-bottom: 50px;

	img {
		width: 100px;
		height: 130px;
	}

	p {
		font-size: 16px;
		font-weight:  bold;
		padding-top: 10px;
		padding-bottom: 30px;
	}

	span{
		width: 80%;
		font-size: 12px;
		text-indent: 15px;
		text-align: justify;
		height: 116px;
		text-justify: inter-word;

		overflow-y: scroll;
		scrollbar-width: 100px;
		::-webkit-scrollbar{
			width: 100px;
		}
	}
}

#body{
	width: 80%;
	height: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;

	form{

		input {
			width: 100%;
		}

	}
}

.form-group{
	display: flex;
	flex-direction: column;
	align-items: center;
}

.form-group:first-child{
	padding-bottom: 20px;
}

.form-ib-group{
	display: flex;
	flex-direction: column;
	align-items: center;
}
</style>
