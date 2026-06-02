import { ref } from 'vue'

export const isOffline = ref(!navigator.onLine)

export function markOffline() {
  isOffline.value = true
}

export function markOnline() {
  isOffline.value = false
}

export function isNetworkError(error: any): boolean {
  return (
    (!error.response && !!error.request) ||
    error.code === 'ECONNABORTED'
  )
}
