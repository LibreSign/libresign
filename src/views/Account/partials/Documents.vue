<script>
// import { documentsService } from '../../../domains/documents'
import { find } from 'lodash-es'

const findDocumentByType = (list, type) => { // TODO: fix contract
	return find(list, row => row.type === type) || {
		id: 0,
		name: 'missing',
		type,
	}
}

export default {
	name: 'Documents',
	components: {
		// Btn,
	},
	data() {
		return {
			documentList: [],
		}
	},
	computed: {
		documents() {
			return {
				default: findDocumentByType(this.documentList, 'default'),
			}
		},
		list() {
			return Object.values(this.documents)
		},
	},
	mounted() {
		// documentsService.loadAccountList()
	},
}
</script>

<template>
	<div class="documents">
		<h1>{{ t('libresign', 'Your profile documents') }}</h1>

		<table class="libre-table is-fullwidth">
			<thead>
				<tr>
					<td>
						{{ t('libresign', 'type') }}
					</td>
					<td>
						{{ t('libresign', 'status') }}
					</td>
					<td>
						{{ t('libresign', 'actions') }}
					</td>
				</tr>
			</thead>
			<tbody>
				<tr v-for="doc in list" :key="`doc-${doc.type}`">
					<td>
						{{ doc.type }}
					</td>
					<td>
						{{ doc.name }}
					</td>
					<td>
						<button>
							<div class="icon-sign icon-user" />
						</button>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<style lang="scss" scoped>
.documents {
	align-items: flex-start;
	width: 100%;

	h1{
		font-size: 1.3rem;
		font-weight: bold;
		border-bottom: 1px solid #000;
		padding-left: 5px;
		width: 100%;
		display: block;
	}
}
</style>
