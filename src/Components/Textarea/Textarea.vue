<template>
	<textarea :value="value" @input="$emit('input', $event.target.value)" />
</template>

<script>
export default {
	name: 'Textarea',
	props: {
		value: {
			type: String,
			required: true,
			default: '',
		},
	},
}
</script>
