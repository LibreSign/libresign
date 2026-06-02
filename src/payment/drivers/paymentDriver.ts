import * as api from '../api'

export const paymentDriver = {
  startPayment: api.startPayment,
  chargeMobilePayment: api.chargeMobilePayment,
  getPaymentStatus: api.getPaymentStatus,
  verifyPayment: api.verifyPayment,
  fetchMobileOptions: api.fetchMobileOptions,
  queryDarajaPayment: api.queryDarajaPayment,
  resumePayment: api.resumePayment,
}
