<template>
	<!-- INLINE VERSION -->
	<div v-if="inline" class="request-picker-buttons">

		<!-- PRIMARY -->
		<div class="primary-picker-option">
			<NcButton variant="primary" @click="handleAction('upload')">
				<template #icon>
					<NcIconSvgWrapper :path="getIcon('upload')" :size="20" />
				</template>
				Upload Document
			</NcButton>
		</div>

		<!-- SEPARATOR -->
		<div class="request-picker-separator">
			<span>or</span>
		</div>

		<!-- SECONDARY -->
		<div class="secondary-picker-options">
			<NcButton variant="secondary" @click="handleAction('uploadUrl')">
				<template #icon>
					<NcIconSvgWrapper :path="getIcon('uploadUrl')" :size="20" />
				</template>
				Upload from URL
			</NcButton>

			<NcButton variant="secondary" :title="allowMultiple ? 'Multiple files allowed' : null"
				@click="handleAction('pickFile')">
				<template #icon>
					<NcIconSvgWrapper :path="getIcon('pickFile')" :size="20" />
				</template>
				Choose from Files
			</NcButton>
		</div>

	</div>

	<!-- DROPDOWN VERSION -->
	<NcActions v-else :menu-name="'Add files'" :variant="variant" v-model:open="openedMenu">
		<template #icon>
			<NcIconSvgWrapper :path="mdiPlus" :size="20" />
		</template>

		<NcActionButton v-for="action in actions" :key="action.key" :wide="true"
			:title="action.key === 'pickFile' && allowMultiple ? 'Multiple files allowed' : null"
			@click="handleAction(action.key)">
			<template #icon>
				<NcIconSvgWrapper :path="action.icon" :size="20" />
			</template>
			{{ action.label }}
		</NcActionButton>
	</NcActions>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import {
	mdiCloudUpload,
	mdiFolder,
	mdiLink,
	mdiPlus,
} from '@mdi/js'

/* ===================== */
/* TYPES */
/* ===================== */
export type UploadAction = 'upload' | 'uploadUrl' | 'pickFile'

/* ===================== */
/* PROPS */
/* ===================== */
const props = withDefaults(defineProps<{
	inline?: boolean
	variant?: string
	allowMultiple?: boolean
}>(), {
	inline: true,
	variant: 'tertiary',
	allowMultiple: false,
})

/* ===================== */
/* EMITS */
/* ===================== */
const emit = defineEmits<{
	(e: UploadAction): void
}>()

/* ===================== */
/* STATE */
/* ===================== */
const openedMenu = ref(false)

/* ===================== */
/* ACTION CONFIG */
/* ===================== */
const actions: { key: UploadAction; label: string; icon: string }[] = [
	{ key: 'upload', label: 'Upload Document', icon: mdiCloudUpload },
	{ key: 'uploadUrl', label: 'Upload from URL', icon: mdiLink },
	{ key: 'pickFile', label: 'Choose from Files', icon: mdiFolder },
]

const iconMap: Record<UploadAction, string> = {
	upload: mdiCloudUpload,
	uploadUrl: mdiLink,
	pickFile: mdiFolder,
}

/* ===================== */
/* HELPERS */
/* ===================== */
function handleAction(action: UploadAction) {
	openedMenu.value = false
	emit(action)
}

function getIcon(action: UploadAction) {
	return iconMap[action]
}
</script>

<style scoped lang="scss">
.request-picker-buttons {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 16px;
	width: 100%;
	max-width: 420px;
	margin: 0 auto;

	.primary-picker-option {
		display: flex;
		justify-content: center;
		width: 100%;

		--color-primary-element-text: #fff;

		:deep(.button-vue--primary) {
			min-width: 260px;
			max-width: 320px;
			font-size: 16px;
			font-weight: 600;
			--button-padding: 0 32px;
		}
	}

	.request-picker-separator {
		display: flex;
		align-items: center;
		width: 100%;
		color: var(--color-text-maxcontrast);
		font-size: 14px;
		gap: 10px;

		span {
			white-space: nowrap;
		}

		&::before,
		&::after {
			content: "";
			flex: 1;
			height: 1px;
			background: var(--color-border);
		}
	}

	.secondary-picker-options {
		display: flex;
		gap: 12px;
		width: 100%;
		justify-content: center;

		:deep(.button-vue) {
			flex: 1;
			height: 44px;
			font-size: 14px;
			background: white;
			border: 1px solid var(--color-border);
			color: var(--color-main-text);
		}

		/* Responsive Breakpoint for Mobile Layouts */
        @media (max-width: 480px) {
            flex-direction: column; /* Stack secondary buttons vertically */
            align-items: center;
            width: 100%;

            :deep(.button-vue) {
                flex: none; /* Prevent width compression */
                width: 100%;
                max-width: 320px; /* Aligns visually with the primary button width */
            }
        }
	}
}
</style>
