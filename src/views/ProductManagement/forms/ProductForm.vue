<template>
	<div class="form">

		<NcTextField v-model="uiForm.code" label="Product Code" :disabled="disabled || isEdit" :error="!!errors.code"
			:helper-text="errors.code" />

		<NcTextField v-model.number="uiForm.amountKES" label="Amount (KES)" :disabled="disabled || isEdit"
			:error="!!errors.amount" :helper-text="errors.amount" />

		<NcTextField v-model="uiForm.currency" label="Currency" :disabled="disabled || isEdit" />

		<NcTextField v-model.number="uiForm.uses" label="Uses" :disabled="disabled" :error="!!errors.uses"
			:helper-text="errors.uses" />

		<NcCheckboxRadioSwitch v-model="uiForm.active" :disabled="disabled">
			Active
		</NcCheckboxRadioSwitch>

	</div>
</template>
<script setup lang="ts">
import { reactive, watch } from 'vue'

import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

interface ProductFormData {
	code: string
	amount: number // cents (backend)
	currency: string
	uses: number
	active: boolean
}

const props = defineProps<{
	modelValue: ProductFormData
	disabled?: boolean
	isEdit?: boolean
}>()

const emit = defineEmits<{
	(e: 'update:modelValue', value: ProductFormData): void
}>()

// ========================
// UI FORM (human-friendly)
// ========================
const uiForm = reactive({
	code: '',
	amountKES: 0,
	currency: 'KES',
	uses: 1,
	active: true,
})

// ========================
// VALIDATION
// ========================
const errors = reactive({
	code: '',
	amount: '',
	uses: '',
})

// ========================
// SYNC FROM PARENT (edit)
// ========================
watch(
	() => props.modelValue,
	(val) => {
		if (!val) return

		uiForm.code = val.code || ''
		uiForm.amountKES = val.amount ? val.amount / 100 : 0
		uiForm.currency = val.currency || 'KES'
		uiForm.uses = val.uses ?? 1
		uiForm.active = val.active ?? true
	},
	{ immediate: true }
)

// ========================
// VALIDATION LOGIC
// ========================
function validate() {
	errors.code = uiForm.code ? '' : 'Product code is required'
	errors.amount = uiForm.amountKES > 0 ? '' : 'Amount must be greater than 0'
	errors.uses = uiForm.uses > 0 ? '' : 'Uses must be at least 1'

	return !errors.code && !errors.amount && !errors.uses
}

// ========================
// SYNC TO PARENT (normalized)
// ========================
watch(
	uiForm,
	() => {
		emit('update:modelValue', {
			code: uiForm.code,
			amount: Math.round(uiForm.amountKES * 100),
			currency: uiForm.currency,
			uses: uiForm.uses,
			active: uiForm.active,
		})
	},
	{ deep: true }
)

// expose validate to parent (for submit)
defineExpose({ validate })
</script>
<style lang="scss" scoped>
.form {
	display: flex;
	flex-direction: column;
	gap: 10px;
}
</style>
