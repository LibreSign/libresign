<template>
  <NcDialog
    v-if="modelValue"
    :name="title"
    @update:open="close"
  >
    <div class="confirm-body">

      <p class="confirm-message">
        {{ message }}
      </p>

      <div class="confirm-actions">

        <NcButton
		  variant="secondary"
          :disabled="loading"
          @click="close"
        >
          {{ cancelText || 'Cancel' }}
        </NcButton>

        <NcButton
          :variant="destructive ? 'error' : 'primary'"
          :disabled="loading"
          @click="confirm"
        >
          {{ confirmText || 'Confirm' }}
        </NcButton>

      </div>

    </div>
  </NcDialog>
</template>
<script setup lang="ts">
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'

const props = defineProps<{
  modelValue: boolean
  title: string
  message: string
  confirmText?: string
  cancelText?: string
  loading?: boolean
  destructive?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'confirm'): void
}>()

function close() {
  emit('update:modelValue', false)
}

function confirm() {
  emit('confirm')
}
</script>
<style scoped>
.confirm-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
}

.confirm-message {
  font-size: 14px;
  line-height: 1.4;
}

.confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
</style>
