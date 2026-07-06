<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="policy-workbench__create-scope-dialog">
		<p class="policy-workbench__create-scope-hint">{{ chooseRuleTypeHint }}</p>
		<div class="policy-workbench__create-scope-grid" role="listbox" :aria-label="ruleTypeAriaLabel">
			<button
				v-for="option in options"
				:key="option.scope"
				type="button"
				role="option"
				class="policy-workbench__create-scope-option"
				:class="{
					'policy-workbench__create-scope-option--disabled': option.disabled,
					'policy-workbench__create-scope-option--selected': selectedScope === option.scope,
				}"
				:disabled="option.disabled"
				:aria-selected="selectedScope === option.scope"
				@click="emit('select-scope', option.scope)">
				<span class="policy-workbench__create-scope-option-icon" aria-hidden="true">
					<NcIconSvgWrapper :path="mdiCheckCircleOutline" :size="16" />
				</span>
				<span class="policy-workbench__create-scope-option-title">{{ option.label }}</span>
				<span class="policy-workbench__create-scope-option-description">{{ option.description }}</span>
			</button>
		</div>
		<ul v-if="notes.length > 0" class="policy-workbench__create-scope-notes">
			<li v-for="note in notes" :key="note.scope">{{ note.label }}: {{ note.reason }}</li>
		</ul>
	</div>
</template>

<script setup lang="ts">
import { mdiCheckCircleOutline } from '@mdi/js'
import { t } from '@nextcloud/l10n'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

type CreateRuleScope = 'system' | 'group' | 'user'

interface CreateScopeOption {
	scope: CreateRuleScope
	label: string
	description: string
	disabled: boolean
}

interface CreateScopeNote {
	scope: 'group' | 'user'
	label: string
	reason: string
}

defineOptions({
	name: 'CatalogCreateScopeSelector',
})

const emit = defineEmits<{
	'select-scope': [scope: CreateRuleScope]
}>()

defineProps<{
	options: CreateScopeOption[]
	selectedScope: CreateRuleScope | null
	notes: CreateScopeNote[]
}>()

// TRANSLATORS Hint shown above the rule type selector before creating a new scoped policy rule.
const chooseRuleTypeHint = t('libresign', 'Choose the rule type to continue.')
// TRANSLATORS Aria label for the list of available rule types during policy rule creation.
const ruleTypeAriaLabel = t('libresign', 'Rule type')
</script>

<style scoped lang="scss">
.policy-workbench {
	&__create-scope-dialog {
		width: min(100%, 38rem);
		margin: 0 auto;
		display: flex;
		flex-direction: column;
		gap: 0.75rem;

		p {
			margin: 0;
		}
	}

	&__create-scope-hint {
		margin: -0.35rem 0 0;
		font-size: 0.84rem;
		color: var(--color-text-maxcontrast);
	}

	&__create-scope-grid {
		display: grid;
		grid-template-columns: 1fr;
		gap: 0.68rem;
	}

	&__create-scope-option {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		gap: 0.2rem;
		width: 100%;
		padding: 0.7rem 0.8rem;
		border-radius: 10px;
		border: 1px solid color-mix(in srgb, var(--color-border-maxcontrast) 45%, transparent);
		background: color-mix(in srgb, var(--color-main-background) 94%, var(--color-background-dark));
		text-align: left;
		cursor: pointer;
		position: relative;
		transition: border-color 0.12s ease, background-color 0.12s ease, box-shadow 0.12s ease, transform 0.12s ease;

		&:hover {
			border-color: color-mix(in srgb, var(--color-primary-element) 58%, var(--color-border-maxcontrast));
			background: color-mix(in srgb, var(--color-primary-element) 9%, var(--color-main-background));
			box-shadow: 0 2px 8px color-mix(in srgb, var(--color-primary-element) 12%, transparent);
			transform: translateY(-1px);
		}

		&:focus:not(:focus-visible):not(:hover) {
			outline: none;
			border-color: color-mix(in srgb, var(--color-border-maxcontrast) 45%, transparent);
			background: color-mix(in srgb, var(--color-main-background) 94%, var(--color-background-dark));
			box-shadow: none;
			transform: none;
		}

		&:focus-visible {
			outline: 2px solid color-mix(in srgb, var(--color-primary-element) 70%, transparent);
			outline-offset: 1px;
		}

		&--selected {
			border-color: color-mix(in srgb, var(--color-primary-element) 58%, var(--color-border-maxcontrast));
			box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-element) 12%, transparent), 0 2px 10px color-mix(in srgb, var(--color-primary-element) 12%, transparent);
			background: color-mix(in srgb, var(--color-primary-element) 9%, var(--color-main-background));
		}

		&--disabled {
			opacity: 0.55;
			cursor: not-allowed;

			&:hover {
				box-shadow: none;
				transform: none;
			}
		}
	}

	&__create-scope-option-icon {
		position: absolute;
		top: 0.55rem;
		right: 0.55rem;
		color: color-mix(in srgb, var(--color-primary-element) 75%, transparent);
		opacity: 0;
		transform: scale(0.9);
		transition: opacity 0.12s ease, transform 0.12s ease;
	}

	&__create-scope-option--selected &__create-scope-option-icon {
		opacity: 1;
		transform: scale(1);
	}

	&__create-scope-option-title {
		font-size: 0.92rem;
		font-weight: 600;
		color: var(--color-main-text);
	}

	&__create-scope-option-description {
		font-size: 0.83rem;
		color: var(--color-text-maxcontrast);
	}

	&__create-scope-notes {
		margin: 0;
		padding-inline-start: 1.1rem;
		font-size: 0.84rem;
		color: var(--color-text-maxcontrast);

		li {
			margin: 0;
		}
	}
}
</style>
