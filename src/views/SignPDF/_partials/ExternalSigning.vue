<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<section class="external-signing" aria-labelledby="external-signing-title">
		<h3 id="external-signing-title">
			<!-- TRANSLATORS Heading for signing using a digital certificate held by an external provider. -->
			{{ t('libresign', 'Sign with a digital certificate') }}
		</h3>
		<p class="external-signing__intro">
			<!-- TRANSLATORS Short explanation for the signer about selecting a certificate issued by a signing provider. -->
			{{ t('libresign', 'Verify your identity and choose a digital certificate to sign this document.') }}
		</p>
		<p class="external-signing__step" role="status">
			<template v-if="!identityAuthorized">
				<!-- TRANSLATORS First of three digital certificate signing steps: confirm signer identity. -->
				{{ t('libresign', 'Step 1 of 3 — Verify identity') }}
			</template>
			<template v-else-if="!session">
				<!-- TRANSLATORS Second of three digital certificate signing steps: select a signing provider and certificate. -->
				{{ t('libresign', 'Step 2 of 3 — Choose certificate') }}
			</template>
			<template v-else>
				<!-- TRANSLATORS Final digital certificate signing step: complete the signing request and check its result. -->
				{{ t('libresign', 'Step 3 of 3 — Complete signing') }}
			</template>
		</p>
		<NcNoteCard v-if="identityAuthorized && syntheticOnly" type="warning">
			<!-- TRANSLATORS Notice that the currently configured CSC provider supplies test certificates, not a personal production identity. -->
			{{ t('libresign', 'Test connection: this provider uses test certificates, not your personal digital identity.') }}
		</NcNoteCard>
		<template v-if="!identityAuthorized">
			<div v-if="accountUid && emailMethod === 'email'" class="external-signing__field">
				<!-- TRANSLATORS Label for choosing how the signer proves their identity before external credential discovery. -->
				<label for="external-signing-method">{{ t('libresign', 'Verification method') }}</label>
				<select id="external-signing-method" v-model="identityMethod" :disabled="busy">
					<option value="account">
						<!-- TRANSLATORS Option to verify the signer using their currently signed-in account. -->
						{{ t('libresign', 'Use my account') }}
					</option>
					<option value="email">
						<!-- TRANSLATORS Option to verify the invited signer using an email confirmation code. -->
						{{ t('libresign', 'Verify by email') }}
					</option>
				</select>
			</div>
			<template v-if="identityMethod === 'email'">
				<NcTextField
					v-model="emailAddress"
					:disabled="busy"
					type="email"
					:label="emailAddressLabel" />
				<NcButton
					:disabled="busy || !emailMatchesRequest"
					@click="requestCode">
					<!-- TRANSLATORS Button that requests the email verification code needed before discovering signing providers. -->
					{{ t('libresign', 'Send verification code') }}
				</NcButton>
				<NcTextField
					v-model="verificationCode"
					:disabled="busy"
					type="text"
					autocomplete="one-time-code"
					:label="verificationCodeLabel" />
			</template>
			<NcButton
				variant="primary"
				:disabled="busy || !mayAuthorize"
				@click="loadIntegrations">
				<!-- TRANSLATORS Button to list signing providers eligible for this document and signer. -->
				{{ t('libresign', 'Continue to signing providers') }}
			</NcButton>
		</template>
		<template v-else>
			<NcNoteCard v-if="integrations.length === 0 && !session" type="info">
				<!-- TRANSLATORS Message when no external provider is permitted for the current signing request. -->
				{{ t('libresign', 'No external signing providers are available for this request.') }}
			</NcNoteCard>
			<template v-if="!session">
				<div v-if="integrations.length > 0" class="external-signing__field">
					<!-- TRANSLATORS Label for selecting an external signature provider. -->
					<label for="external-signing-integration">{{ t('libresign', 'Digital signature provider') }}</label>
					<select
						id="external-signing-integration"
						v-model="selectedIntegrationId"
						:disabled="busy"
						@change="loadCredentials">
						<option value="">
							<!-- TRANSLATORS Placeholder option prompting the signer to select a provider. -->
							{{ t('libresign', 'Choose a provider') }}
						</option>
						<option v-for="integration in integrations" :key="integration.id" :value="integration.id">
							{{ integration.name }}
						</option>
					</select>
				</div>
				<div v-if="credentials.length > 0" class="external-signing__field">
					<!-- TRANSLATORS Label for selecting a remote signing credential/certificate, not uploading a local file. -->
					<label for="external-signing-credential">{{ t('libresign', 'Digital certificate') }}</label>
					<select id="external-signing-credential" v-model="selectedCredentialId" :disabled="busy">
						<option value="">
							<!-- TRANSLATORS Placeholder option prompting the signer to choose an eligible remote certificate. -->
							{{ t('libresign', 'Choose a certificate') }}
						</option>
						<option v-for="credential in credentials" :key="credential.id" :value="credential.id">
							{{ credential.subject }} — {{ credential.validTo }}
						</option>
					</select>
					<div v-if="selectedCredential" class="external-signing__details">
						<!-- TRANSLATORS Caption before the expiration date of the selected remote signing certificate. -->
						<p>{{ t('libresign', 'Valid until') }}: {{ formatCertificateExpiry(selectedCredential.validTo) }}</p>
						<details class="external-signing__technical-details">
							<summary>
								<!-- TRANSLATORS Expandable technical certificate information for advanced users. -->
								{{ t('libresign', 'Certificate details') }}
							</summary>
							<p>{{ selectedCredential.signatureAlgorithm }} / {{ selectedCredential.digestAlgorithm }}</p>
							<p class="external-signing__fingerprint">
								SHA-256: {{ selectedCredential.certificateSha256 }}
							</p>
						</details>
					</div>
				</div>
				<details v-if="selectedCredential && supportedProfiles.length > 1" class="external-signing__field external-signing__technical-details">
					<summary>
						<!-- TRANSLATORS Expandable advanced setting for selecting the PDF digital signature profile. -->
						{{ t('libresign', 'Advanced signature settings') }}
					</summary>
					<!-- TRANSLATORS Label for choosing between PDF Basic and PAdES signature formats. -->
					<label for="external-signing-profile">{{ t('libresign', 'PDF signature format') }}</label>
					<select id="external-signing-profile" v-model="selectedProfile" :disabled="busy">
						<option v-for="profile in supportedProfiles" :key="profile" :value="profile">
							{{ profile === 'pades-baseline-b' ? 'PAdES B-B' : 'PDF Basic' }}
						</option>
					</select>
				</details>
				<NcButton
					v-if="selectedCredential"
					variant="primary"
					:disabled="busy || mustRefreshBeforeContinue || !supportedProfiles.includes(selectedProfile)"
					@click="startSigning">
					<!-- TRANSLATORS Button to prepare the document for remote signing; this does not yet submit a signature. -->
					{{ t('libresign', 'Prepare signature') }}
				</NcButton>
			</template>
			<div v-if="mustRefreshBeforeContinue && !session" class="external-signing__status">
				<NcNoteCard type="warning">
					<!-- TRANSLATORS Warning to refresh an uncertain session before starting another preparation attempt. -->
					{{ t('libresign', 'Check the signing session before another preparation attempt.') }}
				</NcNoteCard>
				<NcButton :disabled="busy" @click="refreshSession">
					<!-- TRANSLATORS Button to refresh the current remote signing session status without resubmitting a signature. -->
					{{ t('libresign', 'Check signing status') }}
				</NcButton>
			</div>
			<div v-if="session" class="external-signing__status" role="status">
				<!-- TRANSLATORS Label preceding the current remote signing session state. -->
				<p>{{ t('libresign', 'Signing status') }}: <strong>{{ statusMessage }}</strong></p>
				<NcNoteCard v-if="requiresReconciliation" type="warning">
					<!-- TRANSLATORS An unknown external signing outcome must not be submitted again. -->
					{{ t('libresign', 'The provider may already have signed. Another signature cannot be submitted automatically. Contact your administrator to reconcile this request.') }}
				</NcNoteCard>
				<NcButton
					v-if="canContinue"
					variant="primary"
					:disabled="busy || mustRefreshBeforeContinue"
					@click="continueSigning">
					<!-- TRANSLATORS Button to continue an already prepared remote signing session. -->
					{{ t('libresign', 'Sign document') }}
				</NcButton>
				<NcButton :disabled="busy" @click="refreshSession">
					<!-- TRANSLATORS Button to refresh the current remote signing session status without resubmitting a signature. -->
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

// TRANSLATORS Label for the email address bound to the invitation; it must match the signer being verified.
const emailAddressLabel = t('libresign', 'Email used for this signature request')
// TRANSLATORS Label for the one-time code received by email before accessing external signing providers.
const verificationCodeLabel = t('libresign', 'Verification code')

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
const selectedProfile = ref<'pdf-basic' | 'pades-baseline-b'>('pades-baseline-b')
const syntheticOnly = ref(false)
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
const supportedProfiles = computed(() => (selectedCredential.value?.profiles ?? []).filter((profile): profile is 'pdf-basic' | 'pades-baseline-b' => profile === 'pdf-basic' || profile === 'pades-baseline-b'))
const canContinue = computed(() => session.value !== null && mayContinueExternalSigning(session.value.status))
const requiresReconciliation = computed(() => session.value !== null && externalSigningRequiresReconciliation(session.value.status))
const formatCertificateExpiry = (value: string): string => {
	const expiry = new Date(value)
	return Number.isNaN(expiry.getTime())
		? value
		: new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeZone: 'UTC' }).format(expiry)
}
watch(selectedCredential, () => {
	const profiles = supportedProfiles.value
	selectedProfile.value = profiles.includes('pades-baseline-b') ? 'pades-baseline-b' : (profiles[0] ?? 'pdf-basic')
})
const statusMessage = computed(() => {
	const state = session.value?.status
	switch (state) {
	// TRANSLATORS Signing-session state: the document is being prepared, before remote signature submission.
	case 'preparing': return t('libresign', 'Preparing the document')
	// TRANSLATORS Signing-session state: the prepared document is ready for a provider signature.
	case 'ready_to_sign': return t('libresign', 'Ready for the remote signature')
	// TRANSLATORS Signing-session state: waiting for the remote provider to authorize the signature.
	case 'authorization_pending': return t('libresign', 'Waiting for provider authorization')
	// TRANSLATORS Signing-session state: the remote signature operation was submitted but has not finished.
	case 'signing_pending': return t('libresign', 'Signature operation pending')
	// TRANSLATORS Signing-session state: a remote signature was received and the PDF is being validated.
	case 'signature_received': return t('libresign', 'Signature received, validating document')
	// TRANSLATORS Signing-session state: the validated signed PDF is being published in LibreSign.
	case 'completing': return t('libresign', 'Finalizing the signed document')
	// TRANSLATORS Signing-session state: the signed document was finalized successfully.
	case 'completed': return t('libresign', 'Document signed successfully')
	// TRANSLATORS Signing-session state: the provider may have signed, so automatic resubmission is prohibited.
	case 'uncertain': return t('libresign', 'Signature result uncertain')
	// TRANSLATORS Signing-session state: the remote signature operation was rejected.
	case 'rejected': return t('libresign', 'Signature rejected')
	// TRANSLATORS Signing-session state: the remote signing session is no longer valid.
	case 'expired': return t('libresign', 'Signature session expired')
	// TRANSLATORS Signing-session state: the remote signature operation failed.
	case 'failed': return t('libresign', 'Signature failed')
	// TRANSLATORS Fallback state when the remote signing session status is unknown.
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
	syntheticOnly.value = false
	selectedIntegrationId.value = ''
	selectedCredentialId.value = ''
	session.value = null
	errorMessage.value = ''
	mustRefreshBeforeContinue.value = false
	attemptId = ''
}

watch([() => props.signRequestUuid, () => props.emailMethod, () => props.boundEmailHash, identityMethod, emailAddress], () => {
	resetSigningContext()
	codeRequested.value = false
	verificationCode.value = ''
})
watch(verificationCode, () => resetSigningContext())
onBeforeUnmount(() => {
	requestGeneration++
})

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
	} catch (error: unknown) {
		if (current === requestGeneration) {
			// Never display raw provider responses: they might contain signing secrets.
			const status = (error as { response?: { status?: number } } | null)?.response?.status
			switch (status) {
			case 403:
				// TRANSLATORS Signer authorization failed or the verification code has expired.
				errorMessage.value = t('libresign', 'Identity verification failed or expired. Check your details and try again.')
				break
			case 409:
				// TRANSLATORS Signing state or document has changed; user must refresh before continuing.
				errorMessage.value = t('libresign', 'The signing request has changed. Check the signing status before continuing.')
				break
			case 429:
				// TRANSLATORS Too many signing or identity verification requests; slow down.
				errorMessage.value = t('libresign', 'Too many attempts. Please try again later.')
				break
			default:
				// TRANSLATORS Generic security-conscious error: remote responses are hidden and an uncertain signature must not be retried blindly.
				errorMessage.value = t('libresign', 'We could not confirm the signing result. Check the status before trying again.')
			}
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
		syntheticOnly.value = result.syntheticOnly
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
			syntheticOnly.value = result.syntheticOnly
			selectedCredentialId.value = result.credentials.length === 1 ? result.credentials[0].id : ''
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
	min-height: 44px;
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

.external-signing__intro {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.external-signing__step {
	margin: 0;
	font-weight: 600;
}

.external-signing__technical-details {
	display: block;
	min-width: 0;
}

.external-signing__technical-details summary {
	cursor: pointer;
	padding-block: 8px;
}

.external-signing__technical-details label {
	display: block;
	margin-block: 8px 4px;
}

.external-signing__fingerprint {
	overflow-wrap: anywhere;
}
</style>
