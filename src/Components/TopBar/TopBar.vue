<!--
- @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
-
- @author Vitor Mattos <vitor@php.rio>
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
-->

<template>
	<div class="top-bar" :style="topBarStyle">
		<slot name="filter" />
		<SidebarToggle v-if="sidebarToggle" />
	</div>
</template>

<script>
import SidebarToggle from './SidebarToggle.vue'

export default {
	name: 'TopBar',
	components: {
		SidebarToggle,
	},
	props: {
		sidebarToggle: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		topBarStyle() {
			return {
				'--original-color-main-background': window.getComputedStyle(document.body).getPropertyValue('--color-main-background'),
			}
		},
	},
	methods: {
		openSidebar() {
		},
	},
}
</script>

<style lang="scss" scoped>
.top-bar {
	display: flex;
	flex-wrap: wrap;
	z-index: 10;
	gap: 3px;
	justify-content: flex-end;
	border-bottom: 1px solid var(--color-border);
	background-color: rgba(var(--color-main-background-rgb),0.8);
	transition: all 0.3s ease-out 0s;
	width: 100%;
	> * {
		margin: 8px;
	}
}
</style>
