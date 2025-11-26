<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container">
		<div class="logo">
			<img :src="logo" :alt="t('libresign', 'LibreSign logo')" draggable="false">
		</div>
		<div id="dataUUID">
			<div v-show="!hasInfo" class="infor-container">
				<div class="section">
					<h1>{{ t('libresign', 'Validate signature') }}</h1>
					<NcNoteCard v-if="validationErrorMessage" type="error">
						{{ validationErrorMessage }}
					</NcNoteCard>
					<NcActions :menu-name="t('libresign', 'Validate signature')"
						:inline="3"
						:force-name="true">
						<NcActionButton :wide="true"
							:disabled="loading"
							@click="openUuidDialog()">
							{{ t('libresign', 'From UUID') }}
							<template #icon>
								<NcLoadingIcon v-if="loading" :size="20" />
								<NcIconSvgWrapper v-else :path="mdiKey" />
							</template>
						</NcActionButton>
						<NcActionButton :wide="true"
							:disabled="loading"
							@click="uploadFile">
							{{ t('libresign', 'Upload') }}
							<template #icon>
								<NcLoadingIcon v-if="loading" :size="20" />
								<NcIconSvgWrapper v-else :path="mdiUpload" />
							</template>
						</NcActionButton>
					</NcActions>
					<NcDialog v-if="getUUID"
						:name="t('libresign', 'Validate signature')"
						is-form
						@closing="getUUID = false">
						<h1>{{ t('libresign', 'Validate signature') }}</h1>
						<NcTextField v-model="uuidToValidate"
							:label="t('libresign', 'Enter the ID or UUID of the document to validate.')"
							:helper-text="helperTextValidation"
							:error="!!uuidToValidate && !canValidate" />
						<template #actions>
							<NcButton variant="primary"
								:disabled="loading || !canValidate"
								@click.prevent="validateAndProceed">
								<template #icon>
									<NcLoadingIcon v-if="loading" :size="20" />
								</template>
								{{ t('libresign', 'Validation') }}
							</NcButton>
						</template>
					</NcDialog>
				</div>
			</div>
			<div v-if="hasInfo" class="infor-container">
				<div class="section">
					<div class="header">
						<NcIconSvgWrapper :path="mdiInformationSlabCircle" :size="30" />
						<h1>{{ t('libresign', 'Document information') }}</h1>
					</div>
					<NcNoteCard v-if="documentValidMessage" type="success">
						{{ documentValidMessage }}
					</NcNoteCard>
					<NcNoteCard v-if="isAfterSigned" type="success">
						{{ t('libresign', 'Congratulations you have digitally signed a document using LibreSign') }}
					</NcNoteCard>
					<ul>
						<NcListItem class="extra"
							compact
							:name="t('libresign', 'Name:')">
							<template #name>
								<strong>{{ t('libresign', 'Name:') }}</strong>
								{{ document.name }}
							</template>
						</NcListItem>
						<NcListItem v-if="document.status"
							class="extra"
							compact
							:name="t('libresign', 'Status:')">
							<template #name>
								<strong>{{ t('libresign', 'Status:') }}</strong>
								{{ documentStatus }}
							</template>
						</NcListItem>
						<NcListItem class="extra"
							compact
							:name="t('libresign', 'Total pages:')">
							<template #name>
								<strong>{{ t('libresign', 'Total pages:') }}</strong>
								{{ document.totalPages }}
							</template>
						</NcListItem>
						<NcListItem class="extra"
							compact
							:name="t('libresign', 'File size:')">
							<template #name>
								<strong>{{ t('libresign', 'File size:') }}</strong>
								{{ size }}
							</template>
						</NcListItem>
						<NcListItem class="extra"
							compact
							:name="t('libresign', 'PDF version:')">
							<template #name>
								<strong>{{ t('libresign', 'PDF version:') }}</strong>
								{{ document.pdfVersion }}
							</template>
						</NcListItem>
					</ul>
					<div class="info-document">
						<NcRichText v-if="document.signers && document.signers.length > 0"
							class="legal-information"
							:text="legalInformation"
							:use-markdown="true" />

						<NcButton variant="primary"
							@click="viewDocument()">
							<template #icon>
								<NcLoadingIcon v-if="loading" :size="20" />
							</template>
							{{ t('libresign', 'View') }}
						</NcButton>
					</div>
				</div>
				<div v-if="document.signers && document.signers.length > 0" class="section">
					<div class="header">
						<NcIconSvgWrapper :path="mdiSignatureFreehand" :size="30" />
						<h1>{{ t('libresign', 'Signatories:') }}</h1>
					</div>
					<ul class="signers">
						<li v-for="(signer, signerIndex) in document.signers"
							:key="signerIndex">
							<NcListItem :name="getName(signer)"
								:active="signer.opened"
								@click="toggleDetail(signer)">
								<template #icon>
									<NcAvatar disable-menu
										:is-no-user="!signer.userId"
										:size="44"
										:user="signer.userId ? signer.userId : getName(signer)"
										:display-name="getName(signer)" />
								</template>
								<template #subname>
									<span class="date-signed-desktop">
										<strong>{{ t('libresign', 'Date signed:') }}</strong>
										<span v-if="signer.signed" class="data-signed">
											{{ dateFromSqlAnsi(signer.signed) }}
										</span>
										<span v-else>{{ t('libresign', 'No date') }}</span>
									</span>
								</template>
								<template #extra-actions>
									<NcButton variant="tertiary"
										:aria-label="signer.opened ? t('libresign', 'Collapse details') : t('libresign', 'Expand details')"
										@click="toggleDetail(signer)">
										<template #icon>
											<NcIconSvgWrapper v-if="signer.opened"
												:path="mdiUnfoldLessHorizontal"
												:size="20" />
											<NcIconSvgWrapper v-else
												:path="mdiUnfoldMoreHorizontal"
												:size="20" />
										</template>
									</NcButton>
								</template>
								<template #indicator>
									<NcIconSvgWrapper v-if="signer.signature_validation"
										:name="signer.signature_validation.label"
										:path="getIconValidityPath(signer)"
										:class="signer.signature_validation?.id === 1 ? 'icon-success' : 'icon-error'"
										:size="20" />
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.request_sign_date"
								class="extra"
								compact
								:name="t('libresign', 'Requested on:')">
								<template #name>
									<strong>{{ t('libresign', 'Requested on:') }}</strong>
									{{ dateFromSqlAnsi(signer.request_sign_date) }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened"
								class="extra"
								compact
								:name="t('libresign', 'Date signed:')">
								<template #name>
									<strong>{{ t('libresign', 'Date signed:') }}</strong>
									<span v-if="signer.signed">
										{{ dateFromSqlAnsi(signer.signed) }}
									</span>
									<span v-else>{{ t('libresign', 'No date') }}</span>
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened"
								class="extra"
								compact
								:name="t('libresign', 'Validation status')"
								:aria-expanded="validationStatusOpenState[signerIndex] ? 'true' : 'false'"
								:aria-label="validationStatusOpenState[signerIndex] ? t('libresign', 'Validation status, expanded. Click to collapse') : t('libresign', 'Validation status, collapsed. Click to expand')"
								role="button"
								@click="toggleState(validationStatusOpenState, signerIndex)">
								<template #name>
									<strong>{{ t('libresign', 'Validation status') }}</strong>
								</template>
								<template #extra-actions>
									<NcButton variant="tertiary"
										:aria-label="validationStatusOpenState[signerIndex] ? t('libresign', 'Collapse validation status') : t('libresign', 'Expand validation status')"
										@click.stop="toggleState(validationStatusOpenState, signerIndex)">
										<template #icon>
											<NcIconSvgWrapper v-if="validationStatusOpenState[signerIndex]"
												:path="mdiUnfoldLessHorizontal"
												:size="20" />
											<NcIconSvgWrapper v-else
												:path="mdiUnfoldMoreHorizontal"
												:size="20" />
										</template>
									</NcButton>
								</template>
							</NcListItem>
							<div v-if="signer.opened && validationStatusOpenState[signerIndex]"
								role="region"
								:aria-label="t('libresign', 'Validation status details')">
								<NcListItem v-if="signer.signature_validation"
									class="extra-chain"
									compact>
									<template #icon>
										<NcIconSvgWrapper :path="signer.signature_validation.id === 1 ? mdiCheckCircle : mdiAlertCircle"
											:class="signer.signature_validation?.id === 1 ? 'icon-success' : 'icon-error'" />
									</template>
									<template #name>
										{{ signer.signature_validation.id === 1 ? t('libresign', 'Document integrity verified') : t('libresign', 'Signature: ') + signer.signature_validation.label }}
									</template>
								</NcListItem>
								<NcListItem v-if="signer.certificate_validation"
									class="extra-chain"
									compact>
									<template #icon>
										<NcIconSvgWrapper :path="signer.certificate_validation.id === 1 ? mdiCheckCircle : mdiAlertCircle"
											:class="signer.certificate_validation?.id === 1 ? 'icon-success' : 'icon-error'" />
									</template>
									<template #name>
										{{ getCertificateTrustMessage(signer) }}
									</template>
								</NcListItem>
								<NcListItem v-if="signer.valid_from && signer.valid_to && signer.signed"
									class="extra-chain"
									compact>
									<template #icon>
										<NcIconSvgWrapper :path="getValidityStatusAtSigning(signer) === 'valid' ? mdiCheckCircle : mdiCancel"
											:class="getValidityStatusAtSigning(signer) === 'valid' ? 'icon-success' : 'icon-error'" />
									</template>
									<template #name>
										{{ getValidityStatusAtSigning(signer) === 'valid' ? t('libresign', 'Valid at signing time') : t('libresign', 'NOT valid at signing time') }}
									</template>
								</NcListItem>
								<NcListItem v-if="signer.crl_validation"
									class="extra-chain"
									compact>
									<template #icon>
										<NcIconSvgWrapper :path="crlStatusMap[signer.crl_validation]?.icon || mdiHelpCircle"
											:class="getCrlValidationIconClass(signer)" />
									</template>
									<template #name>
										{{ crlStatusMap[signer.crl_validation]?.text || signer.crl_validation }}
									</template>
								</NcListItem>
							</div>
							<NcListItem v-if="signer.opened && signer.signatureTypeSN"
								class="extra"
								compact
								:name="t('libresign', 'Hash algorithm:')">
								<template #name>
									<strong>{{ t('libresign', 'Hash algorithm:') }}</strong>
									{{ signer.signatureTypeSN }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.hash"
								class="extra"
								compact
								:name="t('libresign', 'Certificate hash:')">
								<template #name>
									<strong>{{ t('libresign', 'Certificate hash:') }}</strong>
									{{ signer.hash }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.field"
								class="extra"
								compact
								:name="t('libresign', 'Field:')">
								<template #name>
									<strong>{{ t('libresign', 'Field:') }}</strong>
									{{ signer.field }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.remote_address"
								class="extra"
								compact
								:name="t('libresign', 'Remote address:')">
								<template #name>
									<strong>{{ t('libresign', 'Remote address:') }}</strong>
									{{ signer.remote_address }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.user_agent"
								class="extra"
								compact
								:name="t('libresign', 'User agent:')">
								<template #name>
									<strong>{{ t('libresign', 'User agent:') }}</strong>
									{{ signer.user_agent }}
								</template>
							</NcListItem>
							<NcListItem v-if="signer.opened && signer.extensions"
								class="extra"
								compact
								:name="t('libresign', 'Certificate Extensions')"
								:aria-expanded="extensionsOpenState[signerIndex] ? 'true' : 'false'"
								:aria-label="extensionsOpenState[signerIndex] ? t('libresign', 'Certificate Extensions, expanded. Click to collapse') : t('libresign', 'Certificate Extensions, collapsed. Click to expand')"
								role="button"
								@click="toggleState(extensionsOpenState, signerIndex)">
								<template #name>
									<strong>{{ t('libresign', 'Certificate Extensions') }}</strong>
								</template>
								<template #extra-actions>
									<NcButton variant="tertiary"
										:aria-label="extensionsOpenState[signerIndex] ? t('libresign', 'Collapse extensions') : t('libresign', 'Expand extensions')"
										@click.stop="toggleState(extensionsOpenState, signerIndex)">
										<template #icon>
											<NcIconSvgWrapper v-if="extensionsOpenState[signerIndex]"
												:path="mdiUnfoldLessHorizontal"
												:size="20" />
											<NcIconSvgWrapper v-else
												:path="mdiUnfoldMoreHorizontal"
												:size="20" />
										</template>
									</NcButton>
								</template>
							</NcListItem>
							<div v-if="signer.opened && signer.extensions && extensionsOpenState[signerIndex]"
								role="region"
								:aria-label="t('libresign', 'Certificate Extensions details')">
								<NcListItem v-for="(value, key) in signer.extensions"
									:key="key"
									class="extra-chain"
									compact
									:name="camelCaseToTitleCase(key)">
									<template #name>
										<strong>{{ camelCaseToTitleCase(key) }}:</strong>
										<span class="extension-value">{{ value }}</span>
									</template>
								</NcListItem>
							</div>
							<NcListItem v-if="signer.opened && signer.timestamp && signer.timestamp.displayName"
								class="extra"
								compact
								:name="t('libresign', 'Timestamp Authority')"
								:aria-expanded="tsaOpenState[signerIndex] ? 'true' : 'false'"
								:aria-label="tsaOpenState[signerIndex] ? t('libresign', 'Timestamp Authority, expanded. Click to collapse') : t('libresign', 'Timestamp Authority, collapsed. Click to expand')"
								role="button"
								@click="toggleState(tsaOpenState, signerIndex)">
								<template #name>
									<strong>{{ t('libresign', 'Timestamp Authority') }}</strong>
								</template>
								<template #extra-actions>
									<NcButton variant="tertiary"
										:aria-label="tsaOpenState[signerIndex] ? t('libresign', 'Collapse timestamp details') : t('libresign', 'Expand timestamp details')"
										@click.stop="toggleState(tsaOpenState, signerIndex)">
										<template #icon>
											<NcIconSvgWrapper v-if="tsaOpenState[signerIndex]"
												:path="mdiUnfoldLessHorizontal"
												:size="20" />
											<NcIconSvgWrapper v-else
												:path="mdiUnfoldMoreHorizontal"
												:size="20" />
										</template>
									</NcButton>
								</template>
							</NcListItem>
							<div v-if="signer.opened && signer.timestamp && signer.timestamp.displayName && tsaOpenState[signerIndex]"
								role="region"
								:aria-label="t('libresign', 'Timestamp Authority details')">
								<NcListItem v-if="signer.timestamp.displayName"
									class="extra-chain"
									compact
									:name="t('libresign', 'TSA:')">
									<template #name>
										<strong>{{ t('libresign', 'TSA:') }}</strong>
										{{ signer.timestamp.displayName }}
									</template>
								</NcListItem>
								<NcListItem class="extra-chain"
									compact
									:name="t('libresign', 'Time:')">
									<template #name>
										<strong>{{ t('libresign', 'Time:') }}</strong>
										{{ dateFromSqlAnsi(signer.timestamp.genTime) }}
									</template>
								</NcListItem>
								<NcListItem v-if="signer.timestamp.policy"
									class="extra-chain"
									compact
									:name="t('libresign', 'TSA Policy:')">
									<template #name>
										<strong>{{ t('libresign', 'TSA Policy:') }}</strong>
										<span v-if="signer.timestamp.policyName">
											{{ signer.timestamp.policyName }} ({{ signer.timestamp.policy }})
										</span>
										<span v-else>
											{{ signer.timestamp.policy }}
										</span>
									</template>
								</NcListItem>
								<NcListItem v-if="signer.timestamp.serialNumber"
									class="extra-chain"
									compact
									:name="t('libresign', 'TSA Serial:')">
									<template #name>
										<strong>{{ t('libresign', 'TSA Serial:') }}</strong>
										{{ signer.timestamp.serialNumber }}
									</template>
								</NcListItem>
								<NcListItem v-if="signer.timestamp.hashAlgorithm"
									class="extra-chain"
									compact
									:name="t('libresign', 'TSA Hash Algorithm:')">
									<template #name>
										<strong>{{ t('libresign', 'TSA Hash Algorithm:') }}</strong>
										<span>{{ signer.timestamp.hashAlgorithm || signer.timestamp.hashAlgorithmOID }}</span>
										<span v-if="signer.timestamp.hashAlgorithmOID && signer.timestamp.hashAlgorithm && signer.timestamp.hashAlgorithm !== signer.timestamp.hashAlgorithmOID">
											({{ signer.timestamp.hashAlgorithmOID }})
										</span>
									</template>
								</NcListItem>
								<NcListItem v-if="signer.timestamp.accuracy && (signer.timestamp.accuracy.seconds || signer.timestamp.accuracy.millis || signer.timestamp.accuracy.micros)"
									class="extra-chain"
									compact
									:name="t('libresign', 'TSA Accuracy:')">
									<template #name>
										<strong>{{ t('libresign', 'TSA Accuracy:') }}</strong>
										<span v-if="signer.timestamp.accuracy.seconds">{{ signer.timestamp.accuracy.seconds }}s</span>
										<span v-if="signer.timestamp.accuracy.millis"> {{ signer.timestamp.accuracy.millis }}ms</span>
										<span v-if="signer.timestamp.accuracy.micros"> {{ signer.timestamp.accuracy.micros }}μs</span>
									</template>
								</NcListItem>
								<NcListItem v-if="signer.timestamp.ordering !== undefined"
									class="extra-chain"
									compact
									:name="t('libresign', 'TSA Ordering:')">
									<template #name>
										<strong>{{ t('libresign', 'TSA Ordering:') }}</strong>
										{{ signer.timestamp.ordering ? t('libresign', 'Yes') : t('libresign', 'No') }}
									</template>
								</NcListItem>
								<NcListItem v-if="signer.timestamp.nonce"
									class="extra-chain"
									compact
									:name="t('libresign', 'TSA Nonce:')">
									<template #name>
										<strong>{{ t('libresign', 'TSA Nonce:') }}</strong>
										{{ signer.timestamp.nonce }}
									</template>
								</NcListItem>
								<div v-if="signer.timestamp.chain && signer.timestamp.chain.length > 0">
									<NcListItem class="extra-chain"
										compact
										:name="t('libresign', 'TSA Certificate Chain:')">
										<template #name>
											<strong>{{ t('libresign', 'TSA Certificate Chain:') }}</strong>
										</template>
									</NcListItem>
									<NcListItem v-for="(cert, certIndex) in signer.timestamp.chain"
										:key="`tsa-cert-${certIndex}`"
										class="extra-chain"
										compact>
										<template #name>
											<strong>{{ t('libresign', 'Subject:') }}</strong>
											{{ cert.name || cert.subject?.CN || t('libresign', 'Unknown') }}
											<br>
											<small>
												<strong>{{ t('libresign', 'Valid from:') }}</strong>
												{{ formatTimestamp(cert.validFrom_time_t) || t('libresign', 'Unknown') }}
												<br>
												<strong>{{ t('libresign', 'Valid to:') }}</strong>
												{{ formatTimestamp(cert.validTo_time_t) || t('libresign', 'Unknown') }}
											</small>
										</template>
									</NcListItem>
								</div>
							</div>
							<NcListItem v-if="signer.opened && signer.notify"
								class="extra"
								compact
								:name="t('libresign', 'Notifications')"
								:aria-expanded="notificationsOpenState[signerIndex] ? 'true' : 'false'"
								:aria-label="notificationsOpenState[signerIndex] ? t('libresign', 'Notifications, expanded. Click to collapse') : t('libresign', 'Notifications, collapsed. Click to expand')"
								role="button"
								@click="toggleState(notificationsOpenState, signerIndex)">
								<template #name>
									<strong>{{ t('libresign', 'Notifications') }}</strong>
								</template>
								<template #extra-actions>
									<NcButton variant="tertiary"
										:aria-label="notificationsOpenState[signerIndex] ? t('libresign', 'Collapse notifications') : t('libresign', 'Expand notifications')"
										@click.stop="toggleState(notificationsOpenState, signerIndex)">
										<template #icon>
											<NcIconSvgWrapper v-if="notificationsOpenState[signerIndex]"
												:path="mdiUnfoldLessHorizontal"
												:size="20" />
											<NcIconSvgWrapper v-else
												:path="mdiUnfoldMoreHorizontal"
												:size="20" />
										</template>
									</NcButton>
								</template>
							</NcListItem>
							<div v-if="signer.opened && signer.notify && notificationsOpenState[signerIndex]"
								role="region"
								:aria-label="t('libresign', 'Notifications details')">
								<NcListItem v-for="(notify, notifyIndex) in signer.notify"
									:key="notifyIndex"
									class="extra-chain"
									compact
									:name="notify.method">
									<template #name>
										<strong>{{ notify.method }}:</strong>
										{{ dateFromSqlAnsi(notify.date) }}
									</template>
								</NcListItem>
							</div>
							<NcListItem v-if="signer.opened && signer.chain && signer.chain.length > 0"
								class="extra"
								compact
								:name="t('libresign', 'Certificate chain')"
								:aria-expanded="chainOpenState[signerIndex] ? 'true' : 'false'"
								role="button"
								@click="toggleState(chainOpenState, signerIndex)">
								<template #name>
									<strong>{{ t('libresign', 'Certificate chain') }}</strong>
								</template>
								<template #extra-actions>
									<NcButton variant="tertiary"
										:aria-label="chainOpenState[signerIndex] ? t('libresign', 'Collapse certificate chain') : t('libresign', 'Expand certificate chain')">
										<template #icon>
											<NcIconSvgWrapper v-if="chainOpenState[signerIndex]"
												:path="mdiUnfoldLessHorizontal" />
											<NcIconSvgWrapper v-else
												:path="mdiUnfoldMoreHorizontal" />
										</template>
									</NcButton>
								</template>
							</NcListItem>
							<div v-if="signer.opened && signer.chain && chainOpenState[signerIndex]"
								role="region"
								:aria-label="t('libresign', 'Certificate chain details')">
								<NcListItem v-for="(cert, certIndex) in signer.chain"
									:key="certIndex"
									class="extra-chain certificate-item"
									compact
									:name="certIndex === 0 ? t('libresign', 'Signer:') : t('libresign', 'Issuer:')">
									<template #name>
										<div class="cert-details">
											<div>
												<strong>{{ certIndex === 0 ? t('libresign', 'Signer:') : t('libresign', 'Issuer:') }}</strong>
												{{ cert.subject?.CN || cert.name || cert.displayName }}
											</div>
											<div v-if="cert.issuer?.CN" class="cert-issuer">
												<strong>{{ t('libresign', 'Issued by:') }}</strong>
												{{ cert.issuer.CN }}
											</div>
											<div v-if="cert.serialNumber">
												<strong>{{ t('libresign', 'Serial Number:') }}</strong>
												{{ cert.serialNumber }}
												<span v-if="cert.serialNumberHex" class="serial-hex">
													(hex: {{ cert.serialNumberHex }})
												</span>
											</div>
											<div v-if="cert.validFrom_time_t || cert.validTo_time_t">
												<small>
													<strong v-if="cert.validFrom_time_t">{{ t('libresign', 'Valid from:') }}</strong>
													{{ formatTimestamp(cert.validFrom_time_t) }}
													<br v-if="cert.validFrom_time_t && cert.validTo_time_t">
													<strong v-if="cert.validTo_time_t">{{ t('libresign', 'Valid to:') }}</strong>
													{{ formatTimestamp(cert.validTo_time_t) }}
												</small>
											</div>
										</div>
									</template>
								</NcListItem>
							</div>
						</li>
					</ul>
				</div>
				<NcButton v-if="clickedValidate"
					variant="primary"
					@click.prevent="goBack">
					{{ t('libresign', 'Return') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import {
	mdiAlertCircle,
	mdiAlertCircleOutline,
	mdiCancel,
	mdiCheckboxMarkedCircle,
	mdiCheckCircle,
	mdiHelpCircle,
	mdiInformationSlabCircle,
	mdiKey,
	mdiShieldAlert,
	mdiShieldOff,
	mdiSignatureFreehand,
	mdiUnfoldLessHorizontal,
	mdiUnfoldMoreHorizontal,
	mdiUpload,
} from '@mdi/js'
import JSConfetti from 'js-confetti'

import axios from '@nextcloud/axios'
import { formatFileSize } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import Moment from '@nextcloud/moment'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
// eslint-disable-next-line import/no-named-as-default
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import logoGray from '../../img/logo-gray.svg'
import { fileStatus } from '../helpers/fileStatus.js'
import logger from '../logger.js'

export default {
	name: 'Validation',

	components: {
		NcActionButton,
		NcActions,
		NcAvatar,
		NcButton,
		NcDialog,
		NcIconSvgWrapper,
		NcListItem,
		NcLoadingIcon,
		NcNoteCard,
		NcRichText,
		NcTextField,
	},
	setup() {
		return {
			mdiAlertCircle,
			mdiAlertCircleOutline,
			mdiCancel,
			mdiCheckboxMarkedCircle,
			mdiCheckCircle,
			mdiHelpCircle,
			mdiInformationSlabCircle,
			mdiKey,
			mdiShieldAlert,
			mdiShieldOff,
			mdiSignatureFreehand,
			mdiUnfoldLessHorizontal,
			mdiUnfoldMoreHorizontal,
			mdiUpload,
		}
	},
	data() {
		return {
			logo: logoGray,
			uuidToValidate: this.$route.params?.uuid ?? '',
			hasInfo: false,
			loading: false,
			document: {},
			legalInformation: loadState('libresign', 'legal_information', ''),
			clickedValidate: false,
			getUUID: false,
			EXPIRATION_WARNING_DAYS: 30,
			validationStatusOpenState: {},
			extensionsOpenState: {},
			tsaOpenState: {},
			chainOpenState: {},
			notificationsOpenState: {},
			validationErrorMessage: null,
			documentValidMessage: null,
		}
	},
	computed: {
		isAfterSigned() {
			return this.$route.params.isAfterSigned ?? false
		},
		canValidate() {
			if (!this.uuidToValidate) {
				return false
			}
			const isNumericId = /^\d+$/.test(this.uuidToValidate)
			const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
			return isNumericId || (this.uuidToValidate.length === 36 && uuidRegex.test(this.uuidToValidate))
		},
		helperTextValidation() {
			if (this.uuidToValidate && this.uuidToValidate.length > 0 && !this.canValidate) {
				return t('libresign', 'Invalid UUID')
			}
			return ''
		},
		size() {
			return formatFileSize(this.document.size)
		},
		documentStatus() {
			const actual = fileStatus.find(item => item.id === this.document.status)
			if (actual === undefined) {
				return fileStatus.find(item => item.id === -1).label
			}
			return actual.label
		},
		validityStatusMap() {
			return {
				unknown: { text: t('libresign', 'Unknown validity'), variant: 'tertiary', icon: this.mdiHelpCircle },
				expired: { text: t('libresign', 'Expired'), variant: 'error', icon: this.mdiCancel },
				expiring: { text: t('libresign', 'Expiring soon'), variant: 'warning', icon: this.mdiAlertCircleOutline },
				valid: { text: t('libresign', 'Currently valid'), variant: 'success', icon: this.mdiCheckCircle },
			}
		},
		crlStatusMap() {
			return {
				valid: { text: t('libresign', 'Not revoked'), variant: 'success', icon: this.mdiCheckCircle },
				revoked: { text: t('libresign', 'Certificate revoked'), variant: 'error', icon: this.mdiShieldOff },
				missing: { text: t('libresign', 'No CRL information'), variant: 'warning', icon: this.mdiShieldAlert },
				no_urls: { text: t('libresign', 'No CRL URLs found'), variant: 'warning', icon: this.mdiShieldAlert },
				urls_inaccessible: { text: t('libresign', 'CRL URLs inaccessible'), variant: 'tertiary', icon: this.mdiHelpCircle },
				validation_failed: { text: t('libresign', 'CRL validation failed'), variant: 'tertiary', icon: this.mdiHelpCircle },
				validation_error: { text: t('libresign', 'CRL validation error'), variant: 'tertiary', icon: this.mdiHelpCircle },
			}
		},
	},
	watch: {
		'$route.params.uuid'(uuid) {
			this.validate(uuid)
		},
	},
	created() {
		this.document = loadState('libresign', 'file_info', {})
		this.hasInfo = !!this.document?.name
		if (this.hasInfo && this.document.signers) {
			this.document.signers.forEach(signer => {
				this.$set(signer, 'opened', false)
			})
		} else if (this.uuidToValidate.length > 0) {
			this.validate(this.uuidToValidate)
		}
	},
	methods: {
		async upload(file) {
			const formData = new FormData()
			formData.append('file', file)
			await axios.postForm(generateOcsUrl('/apps/libresign/api/v1/file/validate'), formData, {
				headers: {
					'Content-Type': 'multipart/form-data',
				},
			})
				.then(({ data }) => {
					this.clickedValidate = true
					this.handleValidationSuccess(data.ocs.data)
				})
				.catch(({ response }) => {
					const errorMsg = response?.data?.ocs?.data?.errors?.length > 0
						? response.data.ocs.data.errors[0].message
						: t('libresign', 'Failed to validate document')
					this.setValidationError(errorMsg)
				})
		},
		async uploadFile() {
			this.loading = true
			const input = document.createElement('input')
			input.accept = 'application/pdf'
			input.type = 'file'

			input.onchange = async (ev) => {
				const file = ev.target.files[0]

				if (file) {
					await this.upload(file)
				}
				this.loading = false

				input.remove()
			}

			input.click()
		},
		dateFromSqlAnsi(date) {
			return Moment(Date.parse(date)).format('LL LTS')
		},
		toggleDetail(signer) {
			this.$set(signer, 'opened', !signer.opened)
		},
		validate(id) {
			this.validationErrorMessage = null
			this.documentValidMessage = null
			if (id === this.document?.uuid) {
				this.documentValidMessage = t('libresign', 'This document is valid')
				this.hasInfo = true
			} else if (id.length === 36) {
				this.validateByUUID(id)
			} else {
				this.validateByNodeID(id)
			}
			this.getUUID = false
		},
		async validateByUUID(uuid) {
			this.loading = true
			await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/uuid/${uuid}`))
				.then(({ data }) => {
					this.handleValidationSuccess(data.ocs.data)
				})
				.catch(({ response }) => {
					if (response?.status === 404) {
						this.setValidationError(t('libresign', 'Document not found'))
					} else if (response?.data?.ocs?.data?.errors?.length > 0) {
						this.setValidationError(response.data.ocs.data.errors[0].message)
					} else {
						this.setValidationError(t('libresign', 'Failed to validate document'))
					}
				})
			this.loading = false
		},
		async validateByNodeID(nodeId) {
			this.loading = true
			await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${nodeId}`))
				.then(({ data }) => {
					this.handleValidationSuccess(data.ocs.data)
				})
				.catch(({ response }) => {
					if (response?.status === 404) {
						this.setValidationError(t('libresign', 'Document not found'))
					} else if (response?.data?.ocs?.data?.errors?.length > 0) {
						this.setValidationError(response.data.ocs.data.errors[0].message)
					} else {
						this.setValidationError(t('libresign', 'Failed to validate document'))
					}
				})
			this.loading = false
		},
		getName(signer) {
			return signer.displayName || signer.email || signer.signature_validation?.label || t('libresign', 'Unknown')
		},
		getIconValidityPath(signer) {
			if (signer.signature_validation?.id === 1) {
				return mdiCheckboxMarkedCircle
			}
			return mdiAlertCircle
		},
		viewDocument() {
			if (OCA?.Viewer !== undefined) {
				const fileInfo = {
					source: this.document.file,
					basename: this.document.name,
					mime: 'application/pdf',
					fileid: this.document.nodeId,
				}
				OCA.Viewer.open({
					fileInfo,
					list: [fileInfo],
				})
			} else {
				window.open(`${this.document.file}?_t=${Date.now()}`)
			}
		},
		goBack() {
			const urlParams = new URLSearchParams(window.location.search)
			if (urlParams.has('path')) {
				try {
					const redirectPath = window.atob(urlParams.get('path'))
					if (redirectPath && redirectPath.startsWith('/apps')) {
						window.location = generateUrl(redirectPath)
						return
					}
				} catch (error) {
					logger.error('Failed going back', { error })
				}
			}
			this.hasInfo = false
			this.uuidToValidate = this.$route.params?.uuid ?? ''
			this.validationErrorMessage = null
			this.documentValidMessage = null
		},
		getValidityStatus(signer) {
			if (!signer.valid_to) {
				return 'unknown'
			}

			const now = new Date()
			const expirationDate = new Date(signer.valid_to)

			if (expirationDate <= now) {
				return 'expired'
			}

			const warningDate = new Date()
			warningDate.setDate(now.getDate() + this.EXPIRATION_WARNING_DAYS)

			if (expirationDate <= warningDate) {
				return 'expiring'
			}

			return 'valid'
		},
		getValidityStatusAtSigning(signer) {
			if (!signer.signed || !signer.valid_from || !signer.valid_to) {
				return 'unknown'
			}

			const signedDate = new Date(signer.signed)
			const validFrom = new Date(signer.valid_from)
			const validTo = new Date(signer.valid_to)

			if (signedDate < validFrom || signedDate > validTo) {
				return 'expired'
			}

			return 'valid'
		},
		getCertificateTrustMessage(signer) {
			if (!signer.certificate_validation) {
				return t('libresign', 'Trust Chain: Unknown')
			}

			if (signer.certificate_validation.id === 1) {
				if (signer.isLibreSignRootCA) {
					return t('libresign', 'Trust Chain: Trusted (LibreSign CA)')
				}
				return t('libresign', 'Trust Chain: Trusted')
			}

			return t('libresign', 'Trust Chain: ') + signer.certificate_validation.label
		},
		getCrlValidationIconClass(signer) {
			const variant = this.crlStatusMap[signer.crl_validation]?.variant
			if (variant === 'success') return 'icon-success'
			if (variant === 'error') return 'icon-error'
			if (variant === 'warning') return 'icon-warning'
			return 'icon-default'
		},
		camelCaseToTitleCase(text) {
			if (text.includes(' ')) {
				return text.replace(/^./, str => str.toUpperCase())
			}

			return text
				.replace(/([A-Z]+)([A-Z][a-z])/g, '$1 $2')
				.replace(/([a-z])([A-Z])/g, '$1 $2')
				.replace(/^./, str => str.toUpperCase())
				.trim()
		},
		hasValidationIssues(signer) {
			if (signer.signature_validation && signer.signature_validation.id !== 1) {
				return true
			}
			if (signer.certificate_validation && signer.certificate_validation.id !== 1) {
				return true
			}
			if (signer.crl_validation === 'revoked') {
				return true
			}
			if (signer.valid_from && signer.valid_to && signer.signed && this.getValidityStatusAtSigning(signer) !== 'valid') {
				return true
			}
			const currentStatus = this.getValidityStatus(signer)
			if (currentStatus === 'expired' || currentStatus === 'expiring') {
				return true
			}
			return false
		},
		formatTimestamp(timestamp) {
			return timestamp ? new Date(timestamp * 1000).toLocaleString() : ''
		},
		validateAndProceed() {
			this.clickedValidate = true
			this.validate(this.uuidToValidate)
		},
		toggleState(stateObject, index) {
			this.$set(stateObject, index, !stateObject[index])
		},
		setValidationError(message, timeout = 5000) {
			this.validationErrorMessage = message
			if (timeout > 0) {
				setTimeout(() => {
					this.validationErrorMessage = null
				}, timeout)
			}
		},
		openUuidDialog() {
			this.validationErrorMessage = null
			this.getUUID = true
		},
		handleValidationSuccess(data) {
			this.documentValidMessage = t('libresign', 'This document is valid')
			this.document = data
			this.document.signers?.forEach(signer => {
				this.$set(signer, 'opened', false)
			})
			this.hasInfo = true
			if (this.isAfterSigned) {
				const jsConfetti = new JSConfetti()
				jsConfetti.addConfetti()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.container {
	display: flex;
	align-items: center;
	justify-content: center;
	overflow-y: auto;
	width: 100%;
	min-height: 100%;
	padding-top: 20px;

	@media screen and (max-width: 1400px) {
		flex-direction: column;
	}

	.logo {
		width: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
		img {
			width: 50%;
			max-width: 422px;
		}
		@media screen and (max-width: 1400px) {
			padding: 20px 0;
			img {
				width: 60%;
				max-width: 300px;
			}
		}
	}
	#dataUUID {
		width: 100%;
		max-width: 1200px;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 0 20px;
		@media screen and (max-width: 700px) {
			padding: 0;
		}
		h1 {
			font-size: 24px;
			font-weight: bold;
			color: var(--color-main-text);
		}
		form {
			background-color: var(--color-main-background);
			color: var(--color-main-text);
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 20px;
			margin: 20px;
			border-radius: 8px;
			max-width: 500px;
			width: 100%;
			box-shadow: 0 0 6px 0 var(--color-box-shadow);

			@media screen and (max-width: 900px) {
				width: 100%;
				display: flex;
				justify-content: center;
				align-items: center;
				max-width: 100%;
			}
		}
		button {
			float: inline-end;
			align-self: flex-end;
		}
		.infor-container {
			width: 100%;
			margin: 20px 0;
			.section {
				background-color: var(--color-main-background);
				padding: 20px;
				border-radius: 8px;
				box-shadow: 0 0 6px 0 var(--color-box-shadow);
				margin-bottom: 10px;
				width: unset;
				overflow: hidden;
				@media screen and (max-width: 900px) {
					max-width: 100%;
				}
				.action-items {
					gap: 12px;
					flex-direction: column;
				}

				.header {
					display: flex;
					margin-bottom: 2rem;
				}
				h1 {
					font-size: 1.5rem;
				}

				.extra, .extra-chain {
					:deep(.list-item-content__name) {
						white-space: unset;
						display: flex;
						align-items: center;
						gap: 8px;

						.nc-chip {
							display: inline-flex;
						}
					}
					:deep(.list-item__anchor) {
						height: unset;
					}
				}

				.info-document {
					color: var(--color-main-text);
					display: flex;
					flex-direction: column;
					overflow: scroll;
					.legal-information {
						opacity: 0.8;
						align-self: center;
						font-size: 1rem;
						overflow: scroll;
					}

					p {
						font-size: 1rem;
					}
				}

				.signers {
					:deep(.list-item__wrapper) {
						box-sizing: border-box;
					}
					.certificate-item {
						border-bottom: 1px solid var(--color-border);
						padding-bottom: 12px;
						margin-bottom: 12px;
						&:last-child {
							border-bottom: none;
							margin-bottom: 0;
							padding-bottom: 0;
						}
					}
					.extra {
						margin-inline-start: 44px;
						padding-inline-end: 44px;
					}
					.extra-chain {
						margin-inline-start: 88px;
						padding-inline-end: 88px;
					}
					.validation-chips {
						display: flex;
						flex-direction: column;
						gap: 8px;
						margin: 8px 0 8px 64px;
					}
					.icon-success {
						color: green;
					}
					.icon-error {
						color: var(--color-error);
					}
					.icon-warning {
						color: var(--color-warning);
					}
					.icon-default {
						color: var(--color-text-maxcontrast);
					}
					.extension-value {
						white-space: pre-wrap;
						word-break: break-word;
						overflow-wrap: break-word;
					}
					.cert-details {
						display: flex;
						flex-direction: column;
						gap: 8px;
					}
					.cert-issuer {
						font-size: 0.9em;
						opacity: 0.8;
					}
					.serial-hex {
						opacity: 0.7;
					}
				}
			}
		}
	}
}

@media screen and (max-width: 700px) {
	.container {
		align-items: flex-start;
		h1 {
			font-size: 1.3rem;
		}
		.infor-container {
			margin-inline-end: 0;
			.section {
				width: unset;
				box-shadow: none;
				padding: 10px !important;

				.signers {
					.date-signed-desktop {
						display: none;
					}
					.extra {
						margin-inline-start: 8px !important;
						padding-inline-end: 8px !important;
					}
					.extra-chain {
						margin-inline-start: 16px !important;
						padding-inline-end: 8px !important;
					}
				}
			}
		}
	}
	.validation-chips {
		margin: 8px 0 8px 32px !important;
	}
}
</style>
