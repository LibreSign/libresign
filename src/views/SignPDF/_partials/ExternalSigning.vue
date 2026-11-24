<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<section class="external-signing" aria-labelledby="external-signing-title">
		<h3 id="external-signing-title">
			{{ t('libresign', 'External signing (experimental)') }}
		</h3>
		<NcNoteCard type="info">
			<!-- TRANSLATORS The CSC v1 integration is limited to synthetic credentials used for testing. -->
			{{ t('libresign', 'Only approved synthetic laboratory credentials are available. Personal remote signing is not supported yet.') }}
		</NcNoteCard>
		<template v-if="!identityAuthorized">
			<div v-if="accountUid && emailMethod === 'email'" class="external-signing__field">
				<label for="external-signing-method">{{ t('libresign', 'Verification method') }}</label>
				<select id="external-signing-method" v-model="identityMethod" :disabled="busy">
					<option value="account">
						{{ t('libresign', 'Nextcloud account') }}
					</option>
					<option value="email">
						{{ t('libresign', 'Email verification') }}
					</option>
				</select>
			</div>
			<template v-if="identityMethod === 'email'">
				<NcTextField v-model="emailAddress"
					:disabled="busy"
					type="email"
					:label="t('libresign', 'Email used for this signature request')" />
				<NcButton :disabled="busy || !emailMatchesRequest"
					@click="requestCode">
					{{ t('libresign', 'Send verification code') }}
				</NcButton>
				<NcTextField v-model="verificationCode"
					:disabled="busy"
					type="text"
					autocomplete="one-time-code"
					:label="t('libresign', 'Verification code')" />
			</template>
			<NcButton variant="primary"
				:disabled="busy || !mayAuthorize"
				@click="loadIntegrations">
				{{ t('libresign', 'Find available signing providers') }}
			</NcButton>
		</template>
		<template v-else>
			<NcNoteCard v-if="integrations.length === 0 && !session" type="info">
				{{ t('libresign', 'No external signing providers are available for this request.') }}
			</NcNoteCard>
			<template v-if="!session">
				<div v-if="integrations.length > 0" class="external-signing__field">
					<label for="external-signing-integration">{{ t('libresign', 'Signing provider') }}</label>
					<select id="external-signing-integration" v-model="selectedIntegrationId"
						:disabled="busy" @change="loadCredentials">
						<option value="">
							{{ t('libresign', 'Choose a provider') }}
						</option>
						<option v-for="integration in integrations" :key="integration.id" :value="integration.id">
							{{ integration.name }}
						</option>
					</select>
				</div>
				<div v-if="credentials.length > 0" class="external-signing__field">
					<label for="external-signing-credential">{{ t('libresign', 'Signing certificate') }}</label>
					<select id="external-signing-credential" v-model="selectedCredentialId" :disabled="busy">
						<option value="">
							{{ t('libresign', 'Choose a certificate') }}
						</option>
						<option v-for="credential in credentials" :key="credential.id" :value="credential.id">
							{{ credential.subject }} — {{ credential.validTo }}
						</option>
					</select>
					<div v-if="selectedCredential" class="external-signing__details">
						<p>{{ t('libresign', 'Certificate valid until') }}: {{ selectedCredential.validTo }}</p>
						<p>{{ selectedCredential.signatureAlgorithm }} / {{ selectedCredential.digestAlgorithm }}</p>
						<p class="external-signing__fingerprint">
							SHA-256: {{ selectedCredential.certificateSha256 }}
						</p>
					</div>
				</div>
				<div v-if="selectedCredential" class="external-signing__field">
					<label for="external-signing-profile">{{ t('libresign', 'PDF signature format') }}</label>
					<select id="external-signing-profile" v-model="selectedProfile" :disabled="busy">
						<option v-for="profile in supportedProfiles" :key="profile" :value="profile">
							{{ profile === 'pades-baseline-b' ? 'PAdES B-B' : 'PDF Basic' }}
						</option>
					</select>
				</div>
				<NcButton v-if="selectedCredential"
					variant="primary"
					:disabled="busy || mustRefreshBeforeContinue || !supportedProfiles.includes(selectedProfile)"
					@click="startSigning">
					{{ t('libresign', 'Prepare external signature') }}
				</NcButton>
			</template>
			<div v-if="mustRefreshBeforeContinue && !session" class="external-signing__status">
				<NcNoteCard type="warning">
					{{ t('libresign', 'Check the signing session before another preparation attempt.') }}
				</NcNoteCard>
				<NcButton :disabled="busy" @click="refreshSession">
					{{ t('libresign', 'Check signing status') }}
				</NcButton>
			</div>
			<div v-if="session" class="external-signing__status" role="status">
				<p>{{ t('libresign', 'Signing status') }}: <strong>{{ statusMessage }}</strong></p>
				<NcNoteCard v-if="requiresReconciliation" type="warning">
					<!-- TRANSLATORS An unknown external signing outcome must not be submitted again. -->
					{{ t('libresign', 'The provider may already have signed. Another signature cannot be submitted automatically. Contact your administrator to reconcile this request.') }}
				</NcNoteCard>
				<NcButton v-if="canContinue"
					variant="primary" :disabled="busy || mustRefreshBeforeContinue"
					@click="continueSigning">
					{{ t('libresign', 'Continue external signing') }}
				</NcButton>
				<NcButton :disabled="busy" @click="refreshSession">
					{{ t('libresign', 'Check signing status') }}
				</NcButton>
			</div>
		</template>
		<NcNoteCard v-if="errorMessage" type="error" role="alert">
			{{ errorMessage }}
		</NcNoteCard>
	</section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import md5 from 'blueimp-md5'

import { useSignStore } from '../../../store/sign.js'
import {
	continueExternalSigning,
	discoverExternalSigning,
	externalSigningRequiresReconciliation,
	mayContinueExternalSigning,
	startExternalSigning,
	statusExternalSigning,
} from '../../../services/externalSigning.ts'
import type {
	ExternalSigningCredential,
	ExternalSigningIdentity,
	ExternalSigningIntegration,
	ExternalSigningSession,
} from '../../../services/externalSigning.ts'

const props = withDefaults(defineProps<{
	signRequestUuid: string
	emailMethod?: string
	boundEmailHash?: string
}>(), {
	emailMethod: '',
	boundEmailHash: '',
})

const emit = defineEmits<{
	(e: 'signed', payload: { signRequestUuid: string }): void
}>()

const signStore = useSignStore()
const accountUid = getCurrentUser()?.uid ?? ''
const identityMethod = ref<'email' | 'account'>(accountUid ? 'account' : 'email')
const emailAddress = ref('')
const verificationCode = ref('')
const codeRequested = ref(false)
const identityAuthorized = ref(false)
const busy = ref(false)
const errorMessage = ref('')
const integrations = ref<ExternalSigningIntegration[]>([])
const credentials = ref<ExternalSigningCredential[]>([])
const selectedIntegrationId = ref('')
const selectedCredentialId = ref('')
const selectedProfile = ref<'pdf-basic' | 'pades-baseline-b'>('pdf-basic')
const session = ref<ExternalSigningSession | null>(null)
const mustRefreshBeforeContinue = ref(false)
let requestGeneration = 0
let attemptId = ''

const emailMatchesRequest = computed(() => props.emailMethod === 'email'
	&& /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailAddress.value.trim())
	&& (!props.boundEmailHash || md5(emailAddress.value.trim().toLowerCase()) === props.boundEmailHash))
const mayAuthorize = computed(() => props.signRequestUuid.length > 0 && (identityMethod.value === 'account'
	? accountUid.length > 0
	: emailMatchesRequest.value && codeRequested.value && verificationCode.value.trim().length > 0))
const selectedCredential = computed(() => credentials.value.find(item => item.id === selectedCredentialId.value))
const supportedProfiles = computed(() => (selectedCredential.value?.profiles ?? []).filter(
	(profile): profile is 'pdf-basic' | 'pades-baseline-b' => profile === 'pdf-basic' || profile === 'pades-baseline-b',
))
const canContinue = computed(() => session.value !== null && mayContinueExternalSigning(session.value.status))
const requiresReconciliation = computed(() => session.value !== null && externalSigningRequiresReconciliation(session.value.status))
const statusMessage = computed(() => {
	const state = session.value?.status
	switch (state) {
	case 'preparing': return t('libresign', 'Preparing the document')
	case 'ready_to_sign': return t('libresign', 'Ready for the remote signature')
	case 'authorization_pending': return t('libresign', 'Waiting for provider authorization')
	case 'signing_pending': return t('libresign', 'Signature operation pending')
	case 'signature_received': return t('libresign', 'Signature received, validating document')
	case 'completing': return t('libresign', 'Finalizing the signed document')
	case 'completed': return t('libresign', 'Document signed successfully')
	case 'uncertain': return t('libresign', 'Signature result uncertain')
	case 'rejected': return t('libresign', 'Signature rejected')
	case 'expired': return t('libresign', 'Signature session expired')
	case 'failed': return t('libresign', 'Signature failed')
	default: return t('libresign', 'Signing status unavailable')
	}
})

function authorizedIdentity(): ExternalSigningIdentity {
	return {
		uuid: props.signRequestUuid,
		method: identityMethod.value,
		identifyValue: identityMethod.value === 'account' ? accountUid : emailAddress.value.trim(),
		token: identityMethod.value === 'account' ? '' : verificationCode.value.trim(),
	}
}

function resetSigningContext() {
	requestGeneration++
	// A request for a previous SignRequest must not keep the next document
	// blocked while its response is still outstanding.
	busy.value = false
	identityAuthorized.value = false
	integrations.value = []
	credentials.value = []
	selectedIntegrationId.value = ''
	selectedCredentialId.value = ''
	session.value = null
	errorMessage.value = ''
	mustRefreshBeforeContinue.value = false
	attemptId = ''
}

watch([() => props.signRequestUuid, identityMethod, emailAddress], () => {
	resetSigningContext()
	codeRequested.value = false
	verificationCode.value = ''
})
watch(verificationCode, () => resetSigningContext())
onBeforeUnmount(() => { requestGeneration++ })

async function execute<T>(callback: () => Promise<T>, onSuccess: (value: T) => void): Promise<void> {
	if (busy.value) {
		return
	}
	const current = ++requestGeneration
	busy.value = true
	errorMessage.value = ''
	try {
		const result = await callback()
		if (current === requestGeneration) {
			onSuccess(result)
		}
	} catch {
		if (current === requestGeneration) {
			// Error responses are deliberately not rendered verbatim: provider
			// responses can contain untrusted diagnostics and signer secrets.
			errorMessage.value = t('libresign', 'External signing is unavailable or authorization has expired. Check the request and try again if no signing operation is pending.')
		}
	} finally {
		// A late response from another document must not unlock an active
		// operation or replace its visible progress state.
		if (current === requestGeneration) {
			busy.value = false
		}
	}
}

async function requestCode() {
	if (!emailMatchesRequest.value || busy.value) {
		return
	}
	await execute(
		() => axios.post(signStore.buildRequestCodeUrl(props.signRequestUuid), {
			identifyMethod: props.emailMethod,
			signMethod: 'emailToken',
		}),
		() => { codeRequested.value = true },
	)
}

async function loadIntegrations() {
	if (!mayAuthorize.value) {
		return
	}
	const identity = authorizedIdentity()
	await execute(() => discoverExternalSigning(identity), (result) => {
		identityAuthorized.value = true
		integrations.value = result.integrations
		session.value = result.session
		if (result.session) {
			selectedIntegrationId.value = result.session.integrationId
		}
	})
}

async function loadCredentials() {
	credentials.value = []
	selectedCredentialId.value = ''
	if (!selectedIntegrationId.value || !identityAuthorized.value || busy.value) {
		return
	}
	const integrationId = selectedIntegrationId.value
	const identity = authorizedIdentity()
	await execute(() => discoverExternalSigning(identity, integrationId), (result) => {
		if (integrationId === selectedIntegrationId.value) {
			credentials.value = result.credentials
			session.value = result.session
		}
	})
}

async function startSigning() {
	if (session.value || !selectedCredential.value || !selectedIntegrationId.value
		|| !supportedProfiles.value.includes(selectedProfile.value) || busy.value) {
		return
	}
	const identity = authorizedIdentity()
	const integration = selectedIntegrationId.value
	const credential = selectedCredentialId.value
	// Reuse the idempotency key after a network error. We never create a
	// second attempt on a double click or an uncertain response.
	attemptId ||= crypto.randomUUID()
	await execute(
		() => startExternalSigning(identity, integration, credential, attemptId, selectedProfile.value),
		(result) => { session.value = result },
	)
	if (errorMessage.value) {
		mustRefreshBeforeContinue.value = true
	}
}

async function continueSigning() {
	if (!session.value || !canContinue.value || mustRefreshBeforeContinue.value || busy.value) {
		return
	}
	const identity = authorizedIdentity()
	const sessionId = session.value.id
	// A timed-out HTTP response does not imply a failed remote signature.
	// Require an explicit server-side status read before offering continue.
	mustRefreshBeforeContinue.value = true
	await execute(() => continueExternalSigning(identity, sessionId), (result) => {
		session.value = result.session
		if (result.publication?.status === 'completed' && result.session.status === 'completed') {
			emit('signed', { signRequestUuid: props.signRequestUuid })
		}
		mustRefreshBeforeContinue.value = false
	})
}

async function refreshSession() {
	if (!identityAuthorized.value || busy.value) {
		return
	}
	const identity = authorizedIdentity()
	await execute(() => statusExternalSigning(identity), (result) => {
		session.value = result
		mustRefreshBeforeContinue.value = false
	})
}
</script>

<style scoped lang="scss">
.external-signing {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-top: 12px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	min-width: 0;
}

.external-signing__field {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}

.external-signing__field select {
	max-width: 100%;
	min-height: 38px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	padding: 6px;
}

.external-signing__status {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.external-signing__details {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.external-signing__details p {
	margin: 3px 0;
}

.external-signing__fingerprint {
	overflow-wrap: anywhere;
}
</style>
