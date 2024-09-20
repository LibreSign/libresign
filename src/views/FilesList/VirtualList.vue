<template>
	<div class="files-list">
		<div class="files-list__filters">
			<slot name="filters" />
		</div>
	</div>
</template>

<script>
export default {
	name: 'VirtualList',
}
</script>
