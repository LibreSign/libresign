<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="policy-workbench__dialog">
		<div class="policy-workbench__main">
			<header class="policy-workbench__dialog-header">
				<p class="policy-workbench__dialog-description">{{ dialogDescription }}</p>
				<PolicyPrecedenceHint :scopes="priorityNoteScopes" variant="dialog" />
			</header>

			<NcNoteCard
				v-if="removalFeedback"
				type="success"
				class="policy-workbench__removal-feedback"
				aria-live="polite">
				{{ removalFeedback }}
			</NcNoteCard>

			<div v-if="showDefaultInline" class="policy-workbench__default-inline">
				<span class="policy-workbench__default-inline-label">{{ defaultInlineLabel }}</span>
				<strong class="policy-workbench__default-inline-value">{{ currentBaseValue }}</strong>
				<span class="policy-workbench__default-inline-source">({{ defaultSourceLabel }})</span>
				<span v-if="showChangeDefaultAction" class="policy-workbench__default-inline-separator" aria-hidden="true">&middot;</span>
				<NcButton
					v-if="showChangeDefaultAction"
					variant="tertiary"
					size="small"
					class="policy-workbench__default-inline-action"
					@click="emit('change-default')">
					{{ changeDefaultButtonLabel }}
				</NcButton>
			</div>

			<slot />
		</div>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import PolicyPrecedenceHint from '../../components/PolicyPrecedenceHint.vue'

defineOptions({
	name: 'CatalogSettingDialogFrame',
})

const emit = defineEmits<{
	'change-default': []
}>()

defineProps<{
	dialogDescription: string
	priorityNoteScopes: string[]
	removalFeedback: string | null
	showDefaultInline: boolean
	defaultInlineLabel: string
	currentBaseValue: string
	defaultSourceLabel: string
	showChangeDefaultAction: boolean
}>()

// TRANSLATORS Small action button opening the editor to change the default/system policy value.
const changeDefaultButtonLabel = t('libresign', 'Change')
</script>

<style scoped lang="scss">
.policy-workbench {
	&__dialog {
		width: min(1480px, 100%);
		min-height: calc(100vh - 7rem);
		margin: 0 auto;
		display: flex;
		flex-direction: column;
		gap: 0;
	}

	&__main {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
	}

	&__dialog-header {
		display: flex;
		justify-content: flex-start;
		flex-direction: column;
		gap: 1rem;
		align-items: flex-start;

		h2,
		p {
			margin: 0;
		}

		h2 {
			word-break: break-word;
		}
	}

	&__dialog-description {
		max-width: 72ch;
		color: var(--color-text-maxcontrast);
	}

	&__default-inline {
		display: inline-flex;
		align-items: center;
		gap: 0.35rem;
		flex-wrap: wrap;
		margin: 0.05rem 0 0.55rem;
		font-size: 0.9rem;
		line-height: 1.3;
	}

	&__default-inline-label {
		font-weight: 600;
		color: var(--color-main-text);
	}

	&__default-inline-value {
		font-weight: 700;
		color: var(--color-main-text);
	}

	&__default-inline-source {
		color: var(--color-text-maxcontrast);
	}

	&__default-inline-separator {
		color: var(--color-text-maxcontrast);
	}

	&__default-inline-action {
		margin-inline-start: 0.35rem;

		:deep(.button-vue) {
			min-height: auto;
			padding: 0.05rem 0.35rem;
			font-size: 0.84rem;
			font-weight: 600;
		}
	}

	&__removal-feedback {
		margin: 0 0 0.2rem;

		:deep(.notecard) {
			margin: 0;
		}
	}
}

@media (min-width: 961px) {
	.policy-workbench {
		&__dialog {
			flex-direction: row;
			align-items: stretch;
		}

		&__main {
			flex: 1;
			min-width: 0;
			overflow: hidden;
			max-height: calc(100vh - 9rem);
		}
	}
}

@media (max-width: 960px) {
	.policy-workbench {
		&__dialog-header {
			display: flex;
			flex-direction: column;
		}

		&__removal-feedback {
			margin: 0 0 0.2rem;
		}

		&__dialog {
			width: 100%;
			min-height: auto;
			gap: 0;
		}
	}
}

@media (max-width: 640px) {
	.policy-workbench {
		&__dialog {
			gap: 0.6rem;
		}

		&__dialog-header {
			h2,
			p {
				word-break: break-word;
			}
		}
	}
}
</style>
