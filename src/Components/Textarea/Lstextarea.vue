<template>
	<Lstextarea :value="value" @input="$emit('input', $event.target.value)" />
</template>

<script>
export default {
	name: 'Lstextarea',
	props: {
		value: {
			type: String,
			required: true,
			default: '',
		},
	},
}
</script>
