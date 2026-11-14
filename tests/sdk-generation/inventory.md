<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Generated operation inventory

| operationId | HTTP operation | Generated class.method | Result |
| --- | --- | --- | --- |
| certificate_policy-get-certificate-policy | GET /index.php/apps/libresign/certificate-policy.pdf | CertificatesApi.certificatePolicyGetCertificatePolicy | OK |
| crl-get-revocation-list | GET /index.php/apps/libresign/crl/libresign_{instanceId}_{generation}_{engineType}.crl | CertificatesApi.crlGetRevocationList | OK |
| crl-check-certificate-status | GET /index.php/apps/libresign/crl/check/{serialNumber} | VerificationApi.crlCheckCertificateStatus | OK |
| account-create-to-sign | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/account/create/{uuid} | IdentityApi.accountCreateToSign | OK |
| account-signature-generate | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/account/signature | IdentityApi.accountSignatureGenerate | OK |
| account-me | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/account/me | IdentityApi.accountMe | OK |
| account-update-settings | PATCH /ocs/v2.php/apps/libresign/api/{apiVersion}/account/settings | IdentityApi.accountUpdateSettings | OK |
| account-delete-pfx | DELETE /ocs/v2.php/apps/libresign/api/{apiVersion}/account/pfx | IdentityApi.accountDeletePfx | OK |
| account-upload-pfx | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/account/pfx | IdentityApi.accountUploadPfx | OK |
| account-update-pfx-password | PATCH /ocs/v2.php/apps/libresign/api/{apiVersion}/account/pfx | IdentityApi.accountUpdatePfxPassword | OK |
| account-read-pfx-data | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/account/pfx/read | IdentityApi.accountReadPfxData | OK |
| account-set-config | PUT /ocs/v2.php/apps/libresign/api/{apiVersion}/account/config/{key} | IdentityApi.accountSetConfig | OK |
| file-validate-uuid | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/file/validate/uuid/{uuid} | VerificationApi.fileValidateUuid | OK |
| file-validate-file-id | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/file/validate/file_id/{fileId} | VerificationApi.fileValidateFileId | OK |
| file-validate-binary | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/file/validate | VerificationApi.fileValidateBinary | OK |
| file-list | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/file/list | DocumentsApi.fileList | OK |
| file-get-thumbnail | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/file/thumbnail/{nodeId} | DocumentsApi.fileGetThumbnail | OK |
| file-get-thumbnail-by-file-id | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/file/thumbnail/file_id/{fileId} | DocumentsApi.fileGetThumbnailByFileId | OK |
| file-save | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/file | DocumentsApi.fileSave | OK |
| file-add-file-to-envelope | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/file/{uuid}/add-file | DocumentsApi.fileAddFileToEnvelope | OK |
| file-delete-file | DELETE /ocs/v2.php/apps/libresign/api/{apiVersion}/file/file_id/{fileId} | DocumentsApi.fileDeleteFile | OK |
| file_element-create-visible-element | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/file-element/{uuid} | AppearanceApi.fileElementCreateVisibleElement | OK |
| file_element-update-visible-element | PATCH /ocs/v2.php/apps/libresign/api/{apiVersion}/file-element/{uuid}/{elementId} | AppearanceApi.fileElementUpdateVisibleElement | OK |
| file_element-delete-visible-element | DELETE /ocs/v2.php/apps/libresign/api/{apiVersion}/file-element/{uuid}/{elementId} | AppearanceApi.fileElementDeleteVisibleElement | OK |
| file_progress-check-progress-by-uuid | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/file/progress/{uuid} | SigningApi.fileProgressCheckProgressByUuid | OK |
| footer_template-get-footer-template | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/footer-template | AppearanceApi.footerTemplateGetFooterTemplate | OK |
| footer_template-save-footer-template | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/footer-template | AppearanceApi.footerTemplateSaveFooterTemplate | OK |
| footer_template-preview-pdf | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/footer-template/preview-pdf | AppearanceApi.footerTemplatePreviewPdf | OK |
| id_docs-add-files | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/id-docs | IdentityApi.idDocsAddFiles | OK |
| id_docs-list-unauthenticated-signer-documents | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/id-docs | IdentityApi.idDocsListUnauthenticatedSignerDocuments | OK |
| id_docs-delete | DELETE /ocs/v2.php/apps/libresign/api/{apiVersion}/id-docs/{nodeId} | IdentityApi.idDocsDelete | OK |
| id_docs-list-to-approval | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/id-docs/approval/list | IdentityApi.idDocsListToApproval | OK |
| identify-search | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/identify-account/search | IdentityApi.identifySearch | OK |
| notify-signers | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/notify/signers | NotificationsApi.notifySigners | OK |
| notify-signer | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/notify/signer | NotificationsApi.notifySigner | OK |
| notify-status | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/notify/status | NotificationsApi.notifyStatus | OK |
| notify-notification-dismiss | DELETE /ocs/v2.php/apps/libresign/api/{apiVersion}/notify/notification | NotificationsApi.notifyNotificationDismiss | OK |
| policy-effective | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/effective | PoliciesApi.policyEffective | OK |
| policy-get-group | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/group/{groupId}/{policyKey} | PoliciesApi.policyGetGroup | OK |
| policy-set-group | PUT /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/group/{groupId}/{policyKey} | PoliciesApi.policySetGroup | OK |
| policy-clear-group | DELETE /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/group/{groupId}/{policyKey} | PoliciesApi.policyClearGroup | OK |
| policy-list-group-policies | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/by-policy/group/{policyKey} | PoliciesApi.policyListGroupPolicies | OK |
| policy-get-user-policy-for-user | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/user/{userId}/{policyKey} | PoliciesApi.policyGetUserPolicyForUser | OK |
| policy-set-user-policy-for-user | PUT /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/user/{userId}/{policyKey} | PoliciesApi.policySetUserPolicyForUser | OK |
| policy-clear-user-policy-for-user | DELETE /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/user/{userId}/{policyKey} | PoliciesApi.policyClearUserPolicyForUser | OK |
| policy-list-user-policies | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/by-policy/user/{policyKey} | PoliciesApi.policyListUserPolicies | OK |
| policy-set-group-compound | PUT /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/compound/group/{groupId}/{parentPolicyKey} | PoliciesApi.policySetGroupCompound | OK |
| policy-set-user-preference-compound | PUT /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/compound/user/{parentPolicyKey} | PoliciesApi.policySetUserPreferenceCompound | OK |
| policy-set-user-policy-for-user-compound | PUT /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/compound/user/{userId}/{parentPolicyKey} | PoliciesApi.policySetUserPolicyForUserCompound | OK |
| policy-set-user-preference | PUT /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/user/{policyKey} | PoliciesApi.policySetUserPreference | OK |
| policy-clear-user-preference | DELETE /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/user/{policyKey} | PoliciesApi.policyClearUserPreference | OK |
| request_signature-request-signature | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/request-signature | RequestsApi.requestSignatureRequestSignature | OK |
| request_signature-update-signature-request | PATCH /ocs/v2.php/apps/libresign/api/{apiVersion}/request-signature | RequestsApi.requestSignatureUpdateSignatureRequest | OK |
| request_signature-remove-signer | DELETE /ocs/v2.php/apps/libresign/api/{apiVersion}/sign/file_id/{fileId}/{signRequestId} | RequestsApi.requestSignatureRemoveSigner | OK |
| request_signature-delete-signature-request | DELETE /ocs/v2.php/apps/libresign/api/{apiVersion}/sign/file_id/{fileId} | RequestsApi.requestSignatureDeleteSignatureRequest | OK |
| sign_file-sign-by-file-id | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/sign/file_id/{fileId} | SigningApi.signFileSignByFileId | OK |
| sign_file-sign-by-signer-uuid | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/sign/uuid/{uuid} | SigningApi.signFileSignBySignerUuid | OK |
| sign_file-reject-by-file-id | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/sign/file_id/{fileId}/reject | SigningApi.signFileRejectByFileId | OK |
| sign_file-reject-by-signer-uuid | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/sign/uuid/{uuid}/reject | SigningApi.signFileRejectBySignerUuid | OK |
| sign_file-sign-renew | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/sign/uuid/{uuid}/renew/{method} | SigningApi.signFileSignRenew | OK |
| sign_file-request-code-by-signer-uuid | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/sign/uuid/{uuid}/code | SigningApi.signFileRequestCodeBySignerUuid | OK |
| sign_file-request-code-by-file-id | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/sign/file_id/{fileId}/code | SigningApi.signFileRequestCodeByFileId | OK |
| signature_elements-create-signature-element | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/signature/elements | AppearanceApi.signatureElementsCreateSignatureElement | OK |
| signature_elements-get-signature-elements | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/signature/elements | AppearanceApi.signatureElementsGetSignatureElements | OK |
| signature_elements-use-saved-signature-elements | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/signature/elements/saved | AppearanceApi.signatureElementsUseSavedSignatureElements | OK |
| signature_elements-preview-signature-element | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/signature/elements/preview/{nodeId} | AppearanceApi.signatureElementsPreviewSignatureElement | OK |
| signature_elements-get-signature-element | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/signature/elements/{nodeId} | AppearanceApi.signatureElementsGetSignatureElement | OK |
| signature_elements-patch-signature-element | PATCH /ocs/v2.php/apps/libresign/api/{apiVersion}/signature/elements/{nodeId} | AppearanceApi.signatureElementsPatchSignatureElement | OK |
| signature_elements-delete-signature-element | DELETE /ocs/v2.php/apps/libresign/api/{apiVersion}/signature/elements/{nodeId} | AppearanceApi.signatureElementsDeleteSignatureElement | OK |
| signature_stamp_preview-preview-pdf | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/signature-stamp/preview-pdf | AppearanceApi.signatureStampPreviewPreviewPdf | OK |
| admin-generate-certificate-cfssl | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/certificate/cfssl | CertificatesApi.adminGenerateCertificateCfssl | OK |
| admin-generate-certificate-open-ssl | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/certificate/openssl | CertificatesApi.adminGenerateCertificateOpenSsl | OK |
| admin-set-certificate-engine | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/certificate/engine | CertificatesApi.adminSetCertificateEngine | OK |
| admin-load-certificate | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/certificate | CertificatesApi.adminLoadCertificate | OK |
| admin-configure-check | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/configure-check | SystemApi.adminConfigureCheck | OK |
| admin-signature-background-save | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/signature-background | AppearanceApi.adminSignatureBackgroundSave | OK |
| admin-signature-background-get | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/signature-background | AppearanceApi.adminSignatureBackgroundGet | OK |
| admin-signature-background-delete | DELETE /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/signature-background | AppearanceApi.adminSignatureBackgroundDelete | OK |
| admin-save-certificate-policy | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/certificate-policy | CertificatesApi.adminSaveCertificatePolicy | OK |
| admin-delete-certificate-policy | DELETE /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/certificate-policy | CertificatesApi.adminDeleteCertificatePolicy | OK |
| admin-update-oid | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/certificate-policy/oid | CertificatesApi.adminUpdateOid | OK |
| admin-get-active-signings | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/active-signings | RequestsApi.adminGetActiveSignings | OK |
| admin-get-geo-ip-config | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/geoip | SystemApi.adminGetGeoIpConfig | OK |
| admin-save-geo-ip-config | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/admin/geoip | SystemApi.adminSaveGeoIpConfig | OK |
| crl_api-list | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/crl/list | CertificatesApi.crlApiList | OK |
| crl_api-revoke | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/crl/revoke | CertificatesApi.crlApiRevoke | OK |
| policy-get-system | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/system/{policyKey} | PoliciesApi.policyGetSystem | OK |
| policy-set-system | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/system/{policyKey} | PoliciesApi.policySetSystem | OK |
| policy-set-system-compound | POST /ocs/v2.php/apps/libresign/api/{apiVersion}/policies/compound/system/{parentPolicyKey} | PoliciesApi.policySetSystemCompound | OK |
| setting-has-root-cert | GET /ocs/v2.php/apps/libresign/api/{apiVersion}/setting/has-root-cert | CertificatesApi.settingHasRootCert | OK |

90 input operations; 0 inventory errors. Presence does not establish serialization compatibility.
