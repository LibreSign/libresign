<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="content-account">
		<div class="user">
			<UserImage v-bind="{ user }" />
			<div class="details">
				<div class="user-details">
					<h3>{{ t('libresign', 'Details') }}</h3>
					<div class="user-display-name icon-user">
						<p>{{ user.displayName }}</p>
					</div>
				</div>
				<div class="user-password">
					<h3>{{ t('libresign', 'Certificate') }}</h3>
					<ManagePassword />
				</div>
			</div>
		</div>

		<div class="user">
			<Signatures />
			<Documents />
		</div>
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'

import Documents from './partials/Documents.vue'
import ManagePassword from './partials/ManagePassword.vue'
import Signatures from './partials/Signatures.vue'
import UserImage from './partials/UserImage.vue'

export default {
	name: 'Account',

	components: {
		Signatures,
		UserImage,
		Documents,
		ManagePassword,
	},

	data() {
		return {
			user: getCurrentUser(),
		}
	},
}
</script>

<style lang="scss" scoped>

.app-content{
	display: flex;
	flex-direction: row;

	.content-account{
		width: 100%;
		margin-top: 50px;
		display: flex;
		height: 100%;

		.user{
			width: 50%;
			max-width: 350px;
			display: flex;
			flex-direction: column;
			align-items: flex-start;

			&:first-child {
				width: 25%;
				min-width: 240px;
			}

			@media screen and (max-width: 768px) {
				&, &:first-child {
					width: 50%;
				}
			}

			.details{
				display: flex;
				flex-direction: column;
				width: 100%;
				padding: 10px;
				border: 0;
			}

			.user-details{
				display: flex;
				flex-direction: column;
				width: 100%;
				border: 0;

				.user-display-name[class*='icon']{
					width: 100%;
					background-position: 0px 4px;
					opacity: 0.7;
					margin-right: 10%;
					margin-bottom: 12px;
					margin-top: 12px;
					margin-left: 12px;
					padding-left: 22px;
				}
			}

			.user-password{
				display: flex;
				flex-direction: column;
			}
		}

		@media (max-width: 650px) {
			flex-direction: column;

			.user{
				width: 100%;
			}
		}
	}

}
</style>
