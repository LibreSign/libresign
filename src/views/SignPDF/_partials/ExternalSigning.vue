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
			{{ t('libresign', 'Confirm your identity to continue. Your signing service may ask you to approve the signature.') }}
		</p>
		<p class="external-signing__step" role="status">
			<template v-if="!identityAuthorized">
				<!-- TRANSLATORS First of three digital certificate signing steps: confirm signer identity. -->
				{{ t('libresign', 'Step 1 of 3 — Confirm your identity') }}
			</template>
			<template v-else-if="!session">
				<template v-if="integrations.length === 1 && credentials.length === 1 && selectedCredential">
					<!-- TRANSLATORS Second signing step when the sole signing service and certificate were selected automatically. -->
					{{ t('libresign', 'Step 2 of 3 — Review your certificate') }}
				</template>
				<template v-else>
					<!-- TRANSLATORS Second signing step when there are multiple signing choices to make. -->
					{{ t('libresign', 'Step 2 of 3 — Choose how to sign') }}
				</template>
			</template>
			<template v-else>
				<!-- TRANSLATORS Final digital certificate signing step: complete the signing request and check its result. -->
				{{ t('libresign', 'Step 3 of 3 — Sign and check progress') }}
			</template>
		</p>
		<p
			v-if="busy"
			class="external-signing__busy"
			role="status"
			aria-live="polite">
			{{ busyMessage }}
		</p>
		<NcNoteCard v-if="identityAuthorized && syntheticOnly" type="warning">
			<!-- TRANSLATORS Notice that the currently configured CSC provider supplies test certificates, not a personal production identity. -->
			{{ t('libresign', 'Test only: this service uses sample certificates, not your personal digital identity.') }}
		</NcNoteCard>
		<template v-if="!identityAuthorized">
			<NcRadioGroup
				v-if="identityChoices.length > 1"
				v-model="identityMethod"
				:label="t('libresign', 'How would you like to confirm your identity?')"
				class="external-signing__choices">
				<NcCheckboxRadioSwitch
					v-for="choice in identityChoices"
					:key="choice.method"
					name="external-signing-identity"
					:value="choice.method"
					:disabled="busy"
					class="external-signing__choice"
					:class="{ 'external-signing__choice--selected': identityMethod === choice.method }">
					{{ choice.label }}
				</NcCheckboxRadioSwitch>
			</NcRadioGroup>
			<p v-if="identityChoices.length === 1" class="external-signing__help">
				{{ identityChoices[0].label }}
			</p>
			<NcNoteCard v-if="identityChoices.length === 0" type="warning">
				<!-- TRANSLATORS No verification method bound to this request is currently supported by external signing. -->
				{{ t('libresign', 'No supported identity check is available for this signing request. Contact the sender.') }}
			</NcNoteCard>
			<template v-if="identityChoices.length > 0 && identityMethod === 'email'">
				<NcTextField
					v-model="emailAddress"
					:disabled="busy"
					type="email"
					:label="emailAddressLabel" />
				<NcButton
					v-if="!codeRequested"
					:disabled="busy || !emailMatchesRequest"
					@click="requestCode">
					<!-- TRANSLATORS Button that requests the email verification code needed before discovering signing providers. -->
					{{ t('libresign', 'Email me a code') }}
				</NcButton>
				<p v-if="codeRequested" class="external-signing__help" role="status">
					<!-- TRANSLATORS The signer should look for the one-time verification code in their email inbox. -->
					{{ t('libresign', 'Check your email for the code. Enter it below to continue, or request another code.') }}
				</p>
				<NcTextField
					v-if="codeRequested"
					v-model="verificationCode"
					:disabled="busy"
					type="text"
					autocomplete="one-time-code"
					:label="verificationCodeLabel" />
			</template>
			<template v-else-if="identityChoices.length > 0 && identityMethod !== 'account'">
				<NcButton v-if="!codeRequested" :disabled="busy" @click="requestCode">
					<!-- TRANSLATORS Send an identity verification code through the configured SMS or messaging channel. -->
					{{ t('libresign', 'Send me a verification code') }}
				</NcButton>
				<NcTextField
					v-else
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
				{{ t('libresign', 'Continue') }}
			</NcButton>
		</template>
		<template v-else>
			<NcNoteCard v-if="integrations.length === 0 && !session" type="info">
				<!-- TRANSLATORS Message when no external provider is permitted for the current signing request. -->
				{{ t('libresign', 'No digital signature services are available for this document. Contact the person who sent it if you expected to sign this way.') }}
			</NcNoteCard>
			<template v-if="!session">
				<div v-if="integrations.length === 1 && selectedIntegrationId" class="external-signing__field">
					<!-- TRANSLATORS The only authorized provider was already selected; there is no choice to make. -->
					<p>{{ t('libresign', 'Signing service') }}: <strong>{{ integrations[0].name }}</strong></p>
				</div>
				<NcRadioGroup
					v-else-if="integrations.length > 1 && integrations.length <= MAX_VISIBLE_CHOICES"
					:model-value="selectedIntegrationId"
					:label="t('libresign', 'Choose a signing service')"
					:description="t('libresign', 'Choose the service where your digital certificate is stored.')"
					class="external-signing__choices"
					@update:modelValue="chooseSigningService">
					<NcCheckboxRadioSwitch
						v-for="integration in integrations"
						:key="integration.id"
						name="external-signing-provider"
						:value="integration.id"
						:disabled="busy"
						class="external-signing__choice"
						:class="{ 'external-signing__choice--selected': selectedIntegrationId === integration.id }">
						{{ integration.name }}
					</NcCheckboxRadioSwitch>
				</NcRadioGroup>
				<div v-else-if="integrations.length > MAX_VISIBLE_CHOICES" class="external-signing__field">
					<!-- TRANSLATORS Label for selecting an external signature provider from a long list. -->
					<label for="external-signing-integration">{{ t('libresign', 'Choose a signing service') }}</label>
					<!-- TRANSLATORS Explain the service choice when many providers require a compact dropdown. -->
					<p class="external-signing__help">
						{{ t('libresign', 'Choose the service where your digital certificate is stored.') }}
					</p>
					<select
						id="external-signing-integration"
						v-model="selectedIntegrationId"
						:disabled="busy"
						@change="loadCredentials">
						<option value="">
							<!-- TRANSLATORS Placeholder option prompting the signer to select a provider. -->
							{{ t('libresign', 'Select a service') }}
						</option>
						<option v-for="integration in integrations" :key="integration.id" :value="integration.id">
							{{ integration.name }}
						</option>
					</select>
				</div>
				<NcButton
					v-if="selectedIntegrationId && !credentialsLoaded && errorMessage"
					:disabled="busy"
					@click="loadCredentials">
					<!-- TRANSLATORS Retry loading certificates when a signing service temporarily fails to respond. -->
					{{ t('libresign', 'Try loading certificates again') }}
				</NcButton>
				<NcNoteCard v-if="selectedIntegrationId && credentialsLoaded && credentials.length === 0" type="info">
					<!-- TRANSLATORS The provider has no eligible certificates for this signer; signing is unavailable. -->
					{{ integrations.length > 1
						? t('libresign', 'This signing service has no certificate you can use. Try another signing service or contact the sender.')
						: t('libresign', 'This signing service has no certificate you can use for this document. Contact the person who sent it for help.') }}
				</NcNoteCard>
				<div v-if="credentials.length > 0" class="external-signing__field">
					<template v-if="credentials.length === 1 && selectedCredential">
						<!-- TRANSLATORS The only eligible certificate was already selected. -->
						<p>{{ t('libresign', 'Your certificate') }}: <strong>{{ selectedCredential.subject }}</strong></p>
					</template>
					<NcRadioGroup
						v-else-if="credentials.length <= MAX_VISIBLE_CHOICES"
						v-model="selectedCredentialId"
						:label="t('libresign', 'Choose your certificate')"
						class="external-signing__choices">
						<NcCheckboxRadioSwitch
							v-for="credential in credentials"
							:key="credential.id"
							name="external-signing-certificate"
							:value="credential.id"
							:disabled="busy"
							class="external-signing__choice"
							:class="{ 'external-signing__choice--selected': selectedCredentialId === credential.id }">
							{{ credential.subject }}
							<template #description>
								<!-- TRANSLATORS Expiration date of a signer-selectable certificate. -->
								{{ t('libresign', 'Expires {date}', { date: formatCertificateExpiry(credential.validTo) }) }}
							</template>
						</NcCheckboxRadioSwitch>
					</NcRadioGroup>
					<template v-else>
						<!-- TRANSLATORS Label for selecting a remote signing credential from a long list. -->
						<label for="external-signing-credential">{{ t('libresign', 'Choose your certificate') }}</label>
						<select id="external-signing-credential" v-model="selectedCredentialId" :disabled="busy">
							<option value="">
								<!-- TRANSLATORS Placeholder prompting a signer to choose a remote certificate. -->
								{{ t('libresign', 'Select a certificate') }}
							</option>
							<option v-for="credential in credentials" :key="credential.id" :value="credential.id">
								{{ credential.subject }} — {{ formatCertificateExpiry(credential.validTo) }}
							</option>
						</select>
					</template>
					<div v-if="selectedCredential" class="external-signing__details">
						<!-- TRANSLATORS Caption before the expiration date of the selected remote signing certificate. -->
						<p v-if="credentials.length === 1 || credentials.length > MAX_VISIBLE_CHOICES">
							{{ t('libresign', 'Certificate expires') }}: {{ formatCertificateExpiry(selectedCredential.validTo) }}
						</p>
						<details class="external-signing__technical-details">
							<summary>
								<!-- TRANSLATORS Expandable technical certificate information for advanced users. -->
								{{ t('libresign', 'Technical certificate details') }}
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
						{{ t('libresign', 'Advanced options') }}
					</summary>
					<!-- TRANSLATORS Label for choosing between PDF Basic and PAdES signature formats. -->
					<label for="external-signing-profile">{{ t('libresign', 'Signature format') }}</label>
					<p class="external-signing__help">
						<!-- TRANSLATORS The default signature format is safe for most PDF viewers. -->
						{{ t('libresign', 'Keep the recommended format unless the sender requested another one.') }}
					</p>
					<select id="external-signing-profile" v-model="selectedProfile" :disabled="busy">
						<option v-for="profile in supportedProfiles" :key="profile" :value="profile">
							<template v-if="profile === 'pades-baseline-b'">
								<!-- TRANSLATORS Recommended PDF signature setting shown only under advanced options. -->
								{{ t('libresign', 'Recommended PDF signature') }}
							</template>
							<template v-else>
								<!-- TRANSLATORS Basic PDF signature alternative for an advanced user. -->
								{{ t('libresign', 'Basic PDF signature') }}
							</template>
						</option>
					</select>
					<details class="external-signing__technical-details">
						<summary>
							<!-- TRANSLATORS Show technical PDF signature standards only when explicitly requested. -->
							{{ t('libresign', 'Technical format names') }}
						</summary>
						<p>{{ t('libresign', 'Recommended: PAdES B-B. Basic: PDF Basic.') }}</p>
					</details>
				</details>
				<NcButton
					v-if="selectedCredential"
					variant="primary"
					:disabled="busy || mustRefreshBeforeContinue || !supportedProfiles.includes(selectedProfile)"
					@click="startSigning">
					<!-- TRANSLATORS Button to prepare the document for remote signing; this does not yet submit a signature. -->
					{{ t('libresign', 'Continue to sign') }}
				</NcButton>
			</template>
			<div v-if="mustRefreshBeforeContinue && !session" class="external-signing__status">
				<NcNoteCard type="warning">
					<!-- TRANSLATORS Warning to refresh an uncertain session before starting another preparation attempt. -->
					{{ t('libresign', 'We could not confirm whether the document is ready. Check its status before trying again.') }}
				</NcNoteCard>
				<NcButton :disabled="busy" @click="refreshSession">
					<!-- TRANSLATORS Button to refresh the current remote signing session status without resubmitting a signature. -->
					{{ t('libresign', 'Check status') }}
				</NcButton>
			</div>
			<div v-if="session" class="external-signing__status" role="status">
				<!-- TRANSLATORS Label preceding the current remote signing session state. -->
				<p>{{ t('libresign', 'Signature progress') }}: <strong>{{ statusMessage }}</strong></p>
				<NcNoteCard v-if="requiresReconciliation && session.status === 'uncertain'" type="warning">
					<!-- TRANSLATORS An unknown external signing outcome must not be submitted again. -->
					{{ t('libresign', 'We could not confirm whether this document was signed. Do not sign it again. Check the status or contact the person who sent it.') }}
				</NcNoteCard>
				<NcNoteCard v-else-if="session.status === 'authorization_pending'" type="info">
					<!-- TRANSLATORS The signer must finish approving the signature with the remote provider. -->
					{{ t('libresign', 'If your signing service asks for approval, complete it there. Then return here and check the status. Do not start another signature.') }}
				</NcNoteCard>
				<NcNoteCard v-else-if="session.status === 'signing_pending'" type="info">
					<!-- TRANSLATORS The remote provider is still processing the signature; a duplicate submission would be unsafe. -->
					{{ t('libresign', 'Your signing service is processing the document. Check its status here; do not start another signature.') }}
				</NcNoteCard>
				<p v-if="session.status === 'ready_to_sign'" class="external-signing__help">
					<!-- TRANSLATORS Explicit approval may require further confirmation by the chosen service. -->
					{{ t('libresign', 'Select Sign this document to begin. Your signing service may ask you to confirm the signature.') }}
				</p>
				<NcButton
					v-if="canContinue"
					variant="primary"
					:disabled="busy || mustRefreshBeforeContinue"
					@click="continueSigning">
					<!-- TRANSLATORS Button to continue an already prepared remote signing session. -->
					{{ t('libresign', 'Sign this document') }}
				</NcButton>
				<NcButton :disabled="busy" @click="refreshSession">
					<!-- TRANSLATORS Button to refresh the current remote signing session status without resubmitting a signature. -->
					{{ t('libresign', 'Check status') }}
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
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
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
	existingEmailCode?: string
	boundIdentities?: Array<{ method: string, value: string, requirement?: string }> | null
}>(), {
	emailMethod: '',
	boundEmailHash: '',
	existingEmailCode: '',
	boundIdentities: null,
})

const emit = defineEmits<{
	(e: 'signed', payload: { signRequestUuid: string }): void
}>()

// TRANSLATORS Label for the email address bound to the invitation; it must match the signer being verified.
const emailAddressLabel = t('libresign', 'Email address that received the request')

const MAX_VISIBLE_CHOICES = 5
const signStore = useSignStore()
const accountUid = getCurrentUser()?.uid ?? ''
type IdentityChoice = { method: string, value: string, label: string, signMethod: string }
const signMethodByIdentity: Record<string, string> = {
	email: 'emailToken', sms: 'smsToken', whatsapp: 'whatsappToken',
	whatsappbusiness: 'whatsappToken', signal: 'signalToken',
	telegram: 'telegramToken', xmpp: 'xmppToken',
}
const identityLabels: Record<string, string> = {
	account: t('libresign', 'Use my signed-in account'),
	email: t('libresign', 'Use a code sent to my email'),
	sms: t('libresign', 'Use a code sent by SMS'),
	whatsapp: t('libresign', 'Use a code sent by WhatsApp'),
	whatsappbusiness: t('libresign', 'Use a code sent by WhatsApp'),
	signal: t('libresign', 'Use a code sent by Signal'),
	telegram: t('libresign', 'Use a code sent by Telegram'),
	xmpp: t('libresign', 'Use a code sent by XMPP'),
}
const identityChoices = computed<IdentityChoice[]>(() => {
	const bound = props.boundIdentities ?? [
		...(accountUid ? [{ method: 'account', value: accountUid }] : []),
		...(props.emailMethod === 'email' ? [{ method: 'email', value: '' }] : []),
	]
	return bound.filter(candidate => candidate.method === 'account'
		? Boolean(accountUid) && candidate.value === accountUid
		: Boolean(signMethodByIdentity[candidate.method]))
		.filter((candidate, index, candidates) => candidates.findIndex(item => item.method === candidate.method) === index)
		.map(candidate => ({
			method: candidate.method,
			value: candidate.value,
			label: identityLabels[candidate.method] ?? candidate.method,
			signMethod: signMethodByIdentity[candidate.method] ?? '',
		}))
})
const identityMethod = ref<string>(accountUid ? 'account' : 'email')
// TRANSLATORS Label for the one-time code received by email before accessing external signing providers.
const verificationCodeLabel = computed(() => identityMethod.value === 'email'
	? t('libresign', 'Code from your email')
	: t('libresign', 'Verification code'))
const emailAddress = ref(identityChoices.value.find(choice => choice.method === 'email')?.value ?? '')
const verificationCode = ref(props.existingEmailCode)
const codeRequested = ref(props.existingEmailCode !== '')
watch(identityChoices, choices => {
	if (!choices.some(choice => choice.method === identityMethod.value)) {
		identityMethod.value = choices[0]?.method ?? ''
	}
}, { immediate: true })
const identityAuthorized = ref(false)
const busy = ref(false)
type SigningAction = 'code' | 'providers' | 'credentials' | 'prepare' | 'sign' | 'status'
const busyAction = ref<SigningAction | null>(null)
const errorMessage = ref('')
const integrations = ref<ExternalSigningIntegration[]>([])
const credentials = ref<ExternalSigningCredential[]>([])
const credentialsLoaded = ref(false)
const selectedIntegrationId = ref('')
const selectedCredentialId = ref('')
const selectedProfile = ref<'pdf-basic' | 'pades-baseline-b'>('pades-baseline-b')
const syntheticOnly = ref(false)
const session = ref<ExternalSigningSession | null>(null)
const mustRefreshBeforeContinue = ref(false)
let requestGeneration = 0
let attemptId = ''

const selectedIdentity = computed(() => identityChoices.value.find(choice => choice.method === identityMethod.value))
const emailMatchesRequest = computed(() => identityMethod.value === 'email'
	&& /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailAddress.value.trim())
	&& (!props.boundEmailHash || md5(emailAddress.value.trim().toLowerCase()) === props.boundEmailHash)
	&& (!selectedIdentity.value?.value || emailAddress.value.trim().toLowerCase() === selectedIdentity.value.value.toLowerCase()))
const mayAuthorize = computed(() => props.signRequestUuid.length > 0 && Boolean(selectedIdentity.value)
	&& (identityMethod.value === 'account'
		? accountUid.length > 0
		: (identityMethod.value !== 'email' || emailMatchesRequest.value)
			&& codeRequested.value && verificationCode.value.trim().length > 0))
const selectedCredential = computed(() => credentials.value.find(item => item.id === selectedCredentialId.value))
const supportedProfiles = computed(() => (selectedCredential.value?.profiles ?? []).filter((profile): profile is 'pdf-basic' | 'pades-baseline-b' => profile === 'pdf-basic' || profile === 'pades-baseline-b'))
const canContinue = computed(() => session.value !== null && mayContinueExternalSigning(session.value.status))
const requiresReconciliation = computed(() => session.value !== null && externalSigningRequiresReconciliation(session.value.status))
const busyMessage = computed(() => {
	switch (busyAction.value) {
	case 'code': return t('libresign', 'Sending your code…')
	case 'providers': return t('libresign', 'Finding signing services…')
	case 'credentials': return t('libresign', 'Loading your certificates…')
	case 'prepare': return t('libresign', 'Getting your document ready…')
	case 'sign': return t('libresign', 'Contacting your signing service…')
	case 'status': return t('libresign', 'Checking the signature status…')
	default: return ''
	}
})
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
	case 'preparing': return t('libresign', 'Preparing your document')
	// TRANSLATORS Signing-session state: the prepared document is ready for a provider signature.
	case 'ready_to_sign': return t('libresign', 'Ready for you to sign')
	// TRANSLATORS Signing-session state: waiting for the remote provider to authorize the signature.
	case 'authorization_pending': return t('libresign', 'Waiting for your approval')
	// TRANSLATORS Signing-session state: the remote signature operation was submitted but has not finished.
	case 'signing_pending': return t('libresign', 'Your signature is being processed')
	// TRANSLATORS Signing-session state: a remote signature was received and the PDF is being validated.
	case 'signature_received': return t('libresign', 'Checking your signed document')
	// TRANSLATORS Signing-session state: the validated signed PDF is being published in LibreSign.
	case 'completing': return t('libresign', 'Saving your signed document')
	// TRANSLATORS Signing-session state: the signed document was finalized successfully.
	case 'completed': return t('libresign', 'Document signed successfully')
	// TRANSLATORS Signing-session state: the provider may have signed, so automatic resubmission is prohibited.
	case 'uncertain': return t('libresign', 'We could not confirm the result')
	// TRANSLATORS Signing-session state: the remote signature operation was rejected.
	case 'rejected': return t('libresign', 'Signature was not approved')
	// TRANSLATORS Signing-session state: the remote signing session is no longer valid.
	case 'expired': return t('libresign', 'Your signing session expired')
	// TRANSLATORS Signing-session state: the remote signature operation failed.
	case 'failed': return t('libresign', 'Could not complete the signature')
	// TRANSLATORS Fallback state when the remote signing session status is unknown.
	default: return t('libresign', 'Signature status unavailable')
	}
})

function authorizedIdentity(): ExternalSigningIdentity {
	return {
		uuid: props.signRequestUuid,
		method: identityMethod.value,
		identifyValue: identityMethod.value === 'email'
? emailAddress.value.trim()
			: (identityMethod.value === 'account' ? accountUid : selectedIdentity.value?.value ?? ''),
		token: identityMethod.value === 'account' ? '' : verificationCode.value.trim(),
	}
}

function resetSigningContext() {
	requestGeneration++
	// A request for a previous SignRequest must not keep the next document
	// blocked while its response is still outstanding.
	busy.value = false
	busyAction.value = null
	identityAuthorized.value = false
	integrations.value = []
	credentials.value = []
	credentialsLoaded.value = false
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
watch(() => props.existingEmailCode, code => {
	if (identityMethod.value === 'email' && code) {
		verificationCode.value = code
		codeRequested.value = true
	}
}, { immediate: true, flush: 'post' })
watch(verificationCode, () => resetSigningContext())
onBeforeUnmount(() => {
	requestGeneration++
})

async function execute<T>(action: SigningAction, callback: () => Promise<T>, onSuccess: (value: T) => void): Promise<void> {
	if (busy.value) {
		return
	}
	const current = ++requestGeneration
	busy.value = true
	busyAction.value = action
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
				errorMessage.value = t('libresign', 'We could not confirm your identity. Check your details or request a new code.')
				break
			case 409:
				// TRANSLATORS Signing state or document has changed; user must refresh before continuing.
				errorMessage.value = t('libresign', 'This document or signing request has changed. Check its status before continuing.')
				break
			case 429:
				// TRANSLATORS Too many signing or identity verification requests; slow down.
				errorMessage.value = t('libresign', 'Too many attempts. Try again later.')
				break
			default:
				// Do not confuse a failed lookup with a remotely submitted signature.
				if (action === 'code') {
					errorMessage.value = t('libresign', 'We could not send the email code. Check your connection and try again.')
				} else if (action === 'providers' || action === 'credentials') {
					errorMessage.value = t('libresign', 'Could not load signing services. Check your connection and try again.')
				} else if (action === 'prepare') {
					// TRANSLATORS Preparation may already have created a session, so a status check must precede another attempt.
					errorMessage.value = t('libresign', 'We could not confirm whether the document is ready. Check its status before trying again.')
				} else {
					// TRANSLATORS A remote signature result cannot be assumed to have failed after a network error.
					errorMessage.value = t('libresign', 'We could not confirm whether the document was signed. Check its status before trying again.')
				}
			}
		}
	} finally {
		// A late response from another document must not unlock an active
		// operation or replace its visible progress state.
		if (current === requestGeneration) {
			busy.value = false
			busyAction.value = null
		}
	}
}

async function requestCode() {
	if (busy.value || !selectedIdentity.value || identityMethod.value === 'account'
		|| (identityMethod.value === 'email' && !emailMatchesRequest.value)) {
		return
	}
	await execute(
		'code',
		() => axios.post(signStore.buildRequestCodeUrl(props.signRequestUuid), {
			identifyMethod: selectedIdentity.value!.method,
			signMethod: selectedIdentity.value!.signMethod,
		}),
		() => { codeRequested.value = true },
	)
}

async function loadIntegrations() {
	if (!mayAuthorize.value) {
		return
	}
	const identity = authorizedIdentity()
	const generation = requestGeneration + 1
	await execute('providers', async () => {
		const overview = await discoverExternalSigning(identity)
		const providerId = !overview.session && overview.integrations.length === 1
			? overview.integrations[0]?.id ?? ''
			: ''
		// Skip a second provider request if this signer context is obsolete.
		const selection = providerId && generation === requestGeneration
			? await discoverExternalSigning(identity, providerId)
			: null
		return { overview, providerId, selection }
	}, ({ overview, providerId, selection }) => {
		identityAuthorized.value = true
		integrations.value = overview.integrations
		syntheticOnly.value = selection?.syntheticOnly ?? overview.syntheticOnly
		session.value = selection?.session ?? overview.session
		if (overview.session) {
			selectedIntegrationId.value = overview.session.integrationId
		} else if (selection && providerId) {
			selectedIntegrationId.value = providerId
			credentials.value = selection.credentials
			credentialsLoaded.value = true
			selectedCredentialId.value = selection.credentials.length === 1 ? selection.credentials[0].id : ''
		}
	})
}

function chooseSigningService(integrationId: string) {
	if (!busy.value && integrations.value.some(item => item.id === integrationId)) {
		selectedIntegrationId.value = integrationId
		void loadCredentials()
	}
}

async function loadCredentials() {
	if (busy.value) {
		return
	}
	credentials.value = []
	credentialsLoaded.value = false
	selectedCredentialId.value = ''
	if (!selectedIntegrationId.value || !identityAuthorized.value) {
		return
	}
	const integrationId = selectedIntegrationId.value
	const identity = authorizedIdentity()
	await execute('credentials', () => discoverExternalSigning(identity, integrationId), (result) => {
		if (integrationId === selectedIntegrationId.value) {
			credentials.value = result.credentials
			credentialsLoaded.value = true
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
		'prepare',
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
	await execute('sign', () => continueExternalSigning(identity, sessionId), (result) => {
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
	await execute('status', () => statusExternalSigning(identity), (result) => {
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

.external-signing__choices {
	min-width: 0;
	margin: 0;
	padding: 0;
	border: 0;
}

.external-signing__choice {
	box-sizing: border-box;
	width: 100%;
	min-width: 0;
	min-height: 48px;
	margin-block: 6px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
	transition: border-color 0.15s ease;
}

.external-signing__choice--selected {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}

.external-signing__choice :deep(.checkbox-radio-switch__content) {
	box-sizing: border-box;
	width: 100%;
	min-width: 0;
	min-height: 48px;
	padding: 10px 12px;
	border-radius: var(--border-radius-large);
	overflow-wrap: anywhere;
}

.external-signing__choice :deep(.checkbox-content__wrapper) {
	min-width: 0;
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

.external-signing__intro,
.external-signing__help {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.external-signing__busy {
	margin: 0;
	color: var(--color-main-text);
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
