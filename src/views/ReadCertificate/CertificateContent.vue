<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<table v-if="Object.keys(certificate).length">
		<tr>
			<th colspan="2">
				{{ t('libresign', 'Owner of certificate') }}
			</th>
		</tr>
		<tr v-for="(value, customName) in orderList(certificate.subject)" :key="customName">
			<td>{{ getLabelFromId(customName) }}</td>
			<td>{{ Array.isArray(value) ? value.join(', ') : value }}</td>
		</tr>
		<tr v-if="index !== 0">
			<th colspan="2">
				{{ t('libresign', 'Issuer of certificate') }}
			</th>
		</tr>
		<tr v-for="(value, customName) in orderList(certificate.issuer)" :key="value">
			<td>{{ getLabelFromId(customName) }}</td>
			<td>{{ value }}</td>
		</tr>
		<tr v-if="certificate.extracerts">
			<th colspan="2">
				{{ t('libresign', 'Certificate chain:') }}
			</th>
		</tr>
		<tr v-for="(extra, key) in certificate.extracerts"
			:key="`extracerts-${key}`"
			class="certificate-chain">
			<td>
				{{ key }}
			</td>
			<td>
				<CertificateContent :certificate="extra" :index="index + '_' + key" />
			</td>
		</tr>
		<tr>
			<td>{{ t('libresign', 'Certificate valid from:') }}</td>
			<td>{{ certificate.valid_from }}</td>
		</tr>
		<tr>
			<td>{{ t('libresign', 'Certificate valid to:') }}</td>
			<td>{{ certificate.valid_to }}</td>
		</tr>
		<tr>
			<th colspan="2">
				{{ t('libresign', 'Extra information') }}
			</th>
		</tr>
		<tr>
			<td>Name</td>
			<td>{{ certificate.name }}</td>
		</tr>
		<tr v-for="(value, name) in certificate.extensions" :key="name">
			<td>{{ name }}</td>
			<td>{{ value }}</td>
		</tr>
	</table>
</template>

<script>

// import CertificateContent from './CertificateContent.vue'
import { selectCustonOption } from '../../helpers/certification.js'

export default {
	name: 'CertificateContent',
	props: {
		certificate: {
			type: Object,
			default: () => {},
			required: false,
		},
		index: {
			type: String,
			default: '0',
		},
	},
	methods: {
		orderList(data) {
			const sorted = {};
			['CN', 'OU', 'O'].forEach(element => {
				if (data[element]) {
					sorted[element] = data[element]
				}
			})
			Object.keys(data).forEach((key) => {
				if (!sorted[key]) {
					sorted[key] = data[key]
				}
			})
			return sorted
		},
		getLabelFromId(id) {
			try {
				const item = selectCustonOption(id).unwrap()
				return item.label
			} catch (error) {
				return id
			}
		},
	},
}
</script>
<style lang="scss" scoped>
table {
	width: 100%;
	white-space: unset;
}

td {
	padding: 5px;
	border-bottom: 1px solid var(--color-border);
}

td:nth-child(2) {
	word-break: break-all;
}

th {
	font-weight: bold;
}

tr:last-child td {
	border-bottom: none;
}

td:first-child, th:first-child {
	opacity: .5;
	word-break: normal;
}
</style>
