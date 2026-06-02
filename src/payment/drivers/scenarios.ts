export type PaymentScenario =
    | 'daraja-success'
    | 'daraja-timeout'
    | 'dpo-high-confidence'
    | 'dpo-ambiguous'
    | 'dpo-unknown'
    | 'dpo-failed'
    | 'offline-resume'

export const scenarioTiming = {
    'daraja-success': {
        start: 3000,
        charge: 6000,
        poll: 5000,
    },

    'daraja-timeout': {
        start: 3000,
        charge: 8000,
        poll: 10000,
    },

    'dpo-high-confidence': {
        start: 2000,
        charge: 5000,
        poll: 3000,
    },

    'dpo-ambiguous': {
        start: 4000,
        charge: 7000,
        poll: 4000,
    },

	'dpo-unknown': {
        start: 4000,
        charge: 7000,
        poll: 4000,
    },

    'dpo-failed': {
        start: 4000,
        charge: 7000,
        poll: 4000,
    }
}

export const paymentScenario = {
    current:
        (localStorage.getItem('payment-scenario')
            ?? 'dpo-high-confidence') as PaymentScenario,
}
