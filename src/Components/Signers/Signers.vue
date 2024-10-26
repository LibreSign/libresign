<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<ul>
		<Signer v-for="(signer, index) in signers"
			:key="index"
			:current-signer="index"
			:event="event">
			<slot v-bind="{signer}" slot="actions" name="actions" />
		</Signer>
	</ul>
</template>
<script>
import Signer from './Signer.vue'

import { useFilesStore } from '../../store/files.js'

export default {
	name: 'Signers',
	components: {
		Signer,
	},
	props: {
		event: {
			type: String,
			required: false,
			default: '',
		},
	},
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
	computed: {
		signers() {
			return this.filesStore.getFile()?.signers ?? []
		},
	},
}
</script>
