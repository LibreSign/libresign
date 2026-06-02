<template>
	<div class="workflow-stepper">
		<div
			v-for="(step, index) in steps"
			:key="step.id"
			class="workflow-step-wrapper">
			<!-- ========================================= -->
			<!-- CONNECTOR -->
			<!-- ========================================= -->
			<div
				v-if="index !== steps.length - 1"
				class="workflow-step-connector"
				:class="{
					'workflow-step-connector--active':
						isConnectorActive(index),
				}" />

			<!-- ========================================= -->
			<!-- STEP -->
			<!-- ========================================= -->
			<div
				class="workflow-step"
				:class="[
					`workflow-step--${step.status}`,
				]">
				<div class="workflow-step-circle">
					<NcIconSvgWrapper
						v-if="step.completed"
						:path="mdiCheck"
						:size="18" />

					<span v-else>
						{{ index + 1 }}
					</span>
				</div>

				<div class="workflow-step-label">
					{{ step.label }}
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import NcIconSvgWrapper
	from '@nextcloud/vue/components/NcIconSvgWrapper'

import {
	mdiCheck,
} from '@mdi/js'

import type {
	WorkflowStep,
} from '../../../composables/useWorkflowState'

defineOptions({
	name: 'WorkflowStepper',
})

const props = defineProps<{
	steps: WorkflowStep[]
}>()

function isConnectorActive(index: number): boolean {
	const currentStep = props.steps[index]
	const nextStep = props.steps[index + 1]

	return (
		currentStep?.completed
		|| nextStep?.active
	)
}
</script>

<style scoped lang="scss">
.workflow-stepper {
	position: relative;

	display: flex;
	align-items: flex-start;
	justify-content: space-between;

	padding: 8px 0 4px;
	margin-top: -14px;
}

.workflow-step-wrapper {
	position: relative;

	display: flex;
	flex: 1;
	justify-content: center;
}

.workflow-step {
	position: relative;
	z-index: 2;

	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;

	min-width: 72px;
}

.workflow-step-circle {
	display: flex;
	align-items: center;
	justify-content: center;

	width: 30px;
	height: 30px;

	border-radius: 999px;
	border: 1.5px solid var(--color-border);

	background: var(--color-main-background);

	font-size: 1rem;
	font-weight: 700;

	transition:
		background 180ms ease,
		border-color 180ms ease,
		color 180ms ease,
		transform 180ms ease,
		box-shadow 180ms ease,
		opacity 180ms ease;
}

.workflow-step-label {
	font-size: 12px;
	font-weight: 600;
	line-height: 1.2;
	text-align: center;

	color: var(--color-text-maxcontrast);

	transition:
		color 180ms ease,
		opacity 180ms ease;
}

/* =========================================
 * CONNECTOR
 * ========================================= */

.workflow-step-connector {
	position: absolute;

	top: 14px;
	left: 50%;

	width: 100%;
	height: 2px;

	background: var(--color-border);

	z-index: 1;

	transition:
		background 220ms ease,
		opacity 220ms ease;
}

.workflow-step-connector--active {
	background: var(--color-primary-element);
}

/* =========================================
 * CURRENT
 * ========================================= */

.workflow-step--current {
	.workflow-step-circle {
		background: #f59e0b;
		border-color: #f59e0b;
		color: white;

		box-shadow:
			0 0 0 4px rgba(245, 158, 11, 0.12);

		transform: scale(1.03);
	}

	.workflow-step-label {
		color: #a15c00;
		font-weight: 700;
	}
}

/* =========================================
 * COMPLETE
 * ========================================= */

.workflow-step--complete {
	.workflow-step-circle {
		background: var(--color-primary-element);
		border-color: var(--color-primary-element);

		color: white;

		box-shadow: none;
	}

	.workflow-step-label {
		color: var(--color-primary-element);
	}
}

/* =========================================
 * LOCKED
 * ========================================= */

.workflow-step--locked {
	.workflow-step-circle {
		opacity: 0.55;
		background: var(--color-background-hover);
	}

	.workflow-step-label {
		opacity: 0.55;
	}
}

/* =========================================
 * UPCOMING
 * ========================================= */

.workflow-step--upcoming {
	.workflow-step-circle {
		background: var(--color-main-background);
	}

	.workflow-step-label {
		opacity: 0.8;
	}
}
</style>
