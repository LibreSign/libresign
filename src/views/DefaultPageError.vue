<!--
- @copyright Copyright (c) 2021 Lyseon Tech <contato@lt.coop.br>
-
- @author Lyseon Tech <contato@lt.coop.br>
- @author Vinicios Gomes <viniciusgomesvaian@gmail.com>
-
- @license GNU AGPL version 3 or any later version
-
- This program is free software: you can redistribute it and/or modify
- it under the terms of the GNU Affero General Public License as
- published by the Free Software Foundation, either version 3 of the
- License, or (at your option) any later version.
-
- This program is distributed in the hope that it will be useful,
- but WITHOUT ANY WARRANTY; without even the implied warranty of
- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
- GNU Affero General Public License for more details.
-
- You should have received a copy of the GNU Affero General Public License
- along with this program.  If not, see <http://www.gnu.org/licenses/>.
-
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
				{{ error }}
			</NcNoteCard>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'

import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'

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
