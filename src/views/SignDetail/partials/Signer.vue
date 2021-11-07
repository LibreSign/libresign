<script>
export default {
	name: 'Signer',
	props: {
		signer: {
			type: Object,
			required: true,
		},
	},
	computed: {
		displayName() {
			const { signer } = this

			if (signer.displayName) {
				return signer.displayName
			}

			if (signer.fullName) {
				return signer.fullName
			}

			if (signer.email) {
				return signer.email
			}

			return t('libresign', 'Account not exist')
		},
		status() {
			const { signer } = this
			return signer.sign_date ? 'signed' : 'pending'
		},
		signSate() {
			return this.signer.sign_date
		},
	},
}
</script>

<template>
	<li class="signer">
		<div class="signer--header">
			<div class="signer--display-name">
				<span class="icon icon-user" />
				{{ displayName }}
			</div>
			<div class="signer--actions">
				<slot name="actions" />
			</div>
		</div>
		<div class="signer--status">
			<span class="dot" :class="status" /> {{ t('libresign', status) }}
		</div>
		<div class="signer--date">
			<span class="icon icon-calendar-dark" />
			{{ signSate }}
		</div>
		<div class="signer--footer">
			<slot />
		</div>
	</li>
</template>

<style lang="scss" scoped>
.signer {
	display: flex;
	width: 100%;
	flex-direction: column;
	border: 1px solid #cecece;
	border-radius: 10px;
	padding: 5px;
	align-items: flex-start;
	overflow: hidden;
	text-overflow: ellipsis;

	&--header {
		display: flex;
		flex-direction: row;
		align-items: center;
		width: 100%;
		justify-content: space-between;
	}
	&--status {
		text-transform: capitalize;
	}

	.icon, .dot {
		margin-right: 2px;
		display: inline-block;
	}

	.dot{
		width: 10px;
		height: 10px;
		border-radius: 50%;
		cursor: inherit;
		&.signed {
			background: #008000;
		}

		&.canceled{
			background: #ff0000;
		}

		&.pending{
			background: #d85a0b
		}
	}
}
</style>
