<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="isSignaturesAvailable()" class="signatures">
		<h1>{{ t('libresign', 'Your signatures') }}</h1>

		<Signature type="signature">
			<template slot="title">
				{{ t('libresign', 'Signature') }}
			</template>

			<span slot="no-signatures">
				{{ t('libresign', 'No signature, click here to create a new one') }}
			</span>
		</Signature>

		<Signature v-if="false" type="initial">
			<template slot="title">
				{{ t('libresign', 'Initials') }}
			</template>

			<span slot="no-signatures">
				{{ t('libresign', 'No initials, click here to create a new one') }}
			</span>
		</Signature>
	</div>
</template>

<script>
import { getCapabilities } from '@nextcloud/capabilities'

import Signature from './Signature.vue'

export default {
	name: 'Signatures',
	components: {
		Signature,
	},
	methods: {
		isSignaturesAvailable() {
			return getCapabilities()?.libresign?.config?.['sign-elements']?.['is-available'] === true
				&& getCapabilities()?.libresign?.config?.['sign-elements']?.['can-create-signature'] === true
		},
	},
}
</script>

<style lang="scss" scoped>
.signatures {
	align-items: flex-start;
	max-width: 350px;

	h1{
		font-size: 1.3rem;
		font-weight: bold;
		border-bottom: 1px solid #000;
		padding-left: 5px;
		width: 100%;
	}
}
</style>
