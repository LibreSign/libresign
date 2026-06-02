import { defineStore } from 'pinia'

const STORAGE_KEY = 'gopaperless:payment-context'

type PaymentContext = {
  userId: string
  signUuid: string
  signRequestId: number
  productCode: string
}

export const usePaymentContextStore = defineStore('paymentContext', {
  state: (): PaymentContext => ({
    userId: '',
    signUuid: '',
    signRequestId: 0,
    productCode: '',
  }),

  actions: {
    setContext(payload: PaymentContext) {
      this.userId = payload.userId
      this.signUuid = payload.signUuid
      this.signRequestId = payload.signRequestId
      this.productCode = payload.productCode

      // 🔥 persist
      localStorage.setItem(STORAGE_KEY, JSON.stringify(payload))
    },

    hydrate() {
      try {
        const raw = localStorage.getItem(STORAGE_KEY)
        if (!raw) return

        const parsed = JSON.parse(raw)

        this.userId = parsed.userId || ''
        this.signUuid = parsed.signUuid || ''
        this.signRequestId = parsed.signRequestId || 0
        this.productCode = parsed.productCode || ''

        console.log('[PaymentContext] hydrated', parsed)

      } catch (e) {
        console.warn('[PaymentContext] hydration failed', e)
        this.clear()
      }
    },

    clear() {
      this.userId = ''
      this.signUuid = ''
      this.signRequestId = 0
      this.productCode = ''

      localStorage.removeItem(STORAGE_KEY)
    },

    isReady(): boolean {
      return !!(
        this.userId &&
        this.signUuid &&
        this.signRequestId &&
        this.productCode
      )
    },
  },
})
