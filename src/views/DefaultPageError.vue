<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="container">
		<div id="img" />
		<div class="content">
			<h1>404</h1>
			<h2>
				{{ t('libresign', 'Page not found') }}
			</h2>
			<p>{{ paragrath }}</p>
			<NcNoteCard v-for="(error, index) in errors"
				:key="index"
				type="error"
				heading="Error">
				{{ error.message }}
			</NcNoteCard>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'

import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

export default {
	name: 'DefaultPageError',
	components: {
		NcNoteCard,
	},

	data() {
		return {
			paragrath: t('libresign', 'Sorry but the page you are looking for does not exist, has been removed, moved or is temporarily unavailable.'),
		}
	},
	computed: {
		errors() {
			const errors = loadState('libresign', 'errors', [])
			if (errors.length) {
				return errors
			}
			const errorMessage = loadState('libresign', 'error', {})?.message
			if (errorMessage) {
				return [errorMessage]
			}
			return []
		},
	},

}
</script>

<style lang="scss" scoped>
.container{
	display: flex;
	flex-direction: row;
	align-items: center;
	justify-content: center;
	height: 100%;

	#img{
		background-image: url('../../img/sad-face-in-rounded-square.svg');
		height: 140px;
		width: 140px;
		background-repeat: no-repeat;
		background-size: cover;
		line-height: 17.6px;
	}

}

.content{
	box-sizing: border-box;
	font-family: 'Nunito', sans-serif;
	max-width: 560px;
	padding-left: 50px;

	h1{
		font-size: 65px;
		font-weight: 700;
		line-height: 71.5px;
		margin-bottom: 10px;
		margin-top: 0px;
	}

	h2{
		font-size: 21px;
		font-weight: 400;
		line-height: 23.1px;
		margin: 0px;
		margin-bottom: 10px;
	}
	p{
		color: var(--color-main-text);
		font-weight: 400;
		line-height: 17.6px;
	}
}
</style>
