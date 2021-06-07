<template>
	<div class="container-signatures-tab">
		<ul>
			<li v-for="item in items" :key="item.name">
				<div class="user-name">
					<div class="icon-sign icon-user" />
					<span class="name">
						{{ item.name }}
					</span>
				</div>
				<div class="container-dot">
					<div :class="'dot ' + item.status" />
					<span class="statusDot">{{ uppercaseString(item.status) }}</span>
				</div>
				<div class="container-dot">
					<div class="icon-sign icon-calendar-dark" />
					<span v-if="item.data">{{ timestampsToDate(item.data) }}</span>
				</div>
			</li>
		</ul>
	</div>
</template>

<script>
import { fromUnixTime } from 'date-fns'
export default {
	name: 'SignaturesTab',
	props: {
		items: {
			type: Array,
			default: () => [],
			required: true,
		},
	},
	methods: {
		uppercaseString(string) {
			return string[0].toUpperCase() + string.substr(1)
		},
		timestampsToDate(date) {
			return fromUnixTime(date).toLocaleDateString()
		},
	},
}
</script>
<style lang="scss" scoped>
.container-signatures-tab{

	ul{
		display: flex;
		flex-wrap: wrap;
		flex-direction: row;
		padding: 10px;
		border-radius: 10px;

		li{
			display: flex;
			width: 48%;
			flex-direction: column;
			border: 1px solid #cecece;
			margin: 3px;
			border-radius: 10px;
			padding: 5px;
			align-items: flex-start;

			.icon-sign{
				margin-right: 8px;
			}

			.user-name{
				display: flex;
				flex-direction: row;

				.name{
					font-size: 14px;
					font-style: normal;
				}
			}

			.container-dot{
				display: flex;
				flex-direction: row;
				margin: 5px;
				align-items: center;
				justify-content: center;
				cursor: inherit;

				.dot{
					width: 10px;
					height: 10px;
					border-radius: 50%;
					margin-right: 10px;
					cursor: inherit;
				}

				.done{
					background: #008000;
				}

				.canceled{
					background: #ff0000;
				}

				.pending {
					background: #d85a0b
				}

				span{
					font-size: 14px;
					font-weight: normal;
					text-align: center;
					color: rgba(0,0,0,.7);
					cursor: inherit;
				}
			}

		}
	}
}

</style>
