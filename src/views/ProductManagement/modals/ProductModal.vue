<template>
  <NcDialog
    v-if="modelValue"
    :name="mode === 'create' ? 'Create Product' : 'Edit Product'"
    @update:open="close"
  >
    <div class="modal-body">

      <ProductForm
        ref="formRef"
		:disabled="submitting"
        v-model="form"
        :isEdit="mode === 'edit'"
      />

      <div class="modal-actions">
        <NcButton @click="close" :disabled="submitting" variant="secondary">Cancel</NcButton>

        <NcButton :disabled="submitting" @click="handleSubmit" variant="primary">
			<template v-if="submitting">
				<NcLoadingIcon :size="20" style="margin-right: 6px;" />
				Saving...
			</template>
			<template v-else>
				{{ mode === 'create' ? 'Create' : 'Save Changes' }}
			</template>
		</NcButton>
      </div>

    </div>
  </NcDialog>
</template>
<script setup lang="ts">
import { ref, watch } from 'vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import ProductForm from '../forms/ProductForm.vue'
import { notifySuccess, notifyError } from '@/services/toast'

interface Product {
  id?: number
  code: string
  amount: number
  currency: string
  uses: number
  active: boolean
}

const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  product?: Product | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'success'): void
}>()

const submitting = ref(false)
const formRef = ref()

// ========================
// FORM STATE
// ========================
const form = ref<Product>({
  code: '',
  amount: 0,
  currency: 'KES',
  uses: 1,
  active: true,
})

// ========================
// WATCH (mode switch)
// ========================
watch(
  () => props.modelValue,
  (open) => {
    if (!open) return

    if (props.mode === 'edit' && props.product) {
      form.value = { ...props.product }
    } else {
      form.value = {
        code: '',
        amount: 0,
        currency: 'KES',
        uses: 1,
        active: true,
      }
    }
  }
)

// ========================
// CLOSE
// ========================
function close() {
  emit('update:modelValue', false)
}

// ========================
// SUBMIT
// ========================
async function handleSubmit() {
  if (submitting.value) return
  const isValid = formRef.value?.validate()

  if (!isValid) {
    notifyError({ message: 'Please fix form errors' })
    return
  }

  try {
    submitting.value = true

    if (props.mode === 'create') {
      await axios.post(
        generateOcsUrl('/apps/libresign/api/v1/product/create'),
        {...form.value, name: form.value.code} // name is required by backend but we don't want to show it in the form, so we set it to code
      )

      notifySuccess({ message: 'Product created successfully' })
    } else {
      await axios.put(
        generateOcsUrl('/apps/libresign/api/v1/product/update'),
        {
          id: props.product?.id,
          ...form.value,
        }
      )

      notifySuccess({ message: 'Product updated successfully' })
    }

    emit('success')
	close()

  } catch (err: any) {
    notifyError({
		message:
			props.mode === 'create'
			? 'Failed to create product'
			: 'Failed to update product',
		important: true,
	})
  } finally {
    submitting.value = false
  }
}
</script>
<style lang="scss" scoped>
.modal-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 8px;
}

:deep(.nc-dialog__content) {
  max-width: 420px;
}
</style>
