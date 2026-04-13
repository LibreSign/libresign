<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign;

/**
 * Base contracts
 *
 * @psalm-type LibresignPagination = array{
 *     total: non-negative-int,
 *     current: ?string,
 *     next: ?string,
 *     prev: ?string,
 *     last: ?string,
 *     first: ?string,
 * }
 * @psalm-type LibresignSettings = array{
 *     canSign: bool,
 *     canRequestSign: bool,
 *     phoneNumber: string,
 *     hasSignatureFile: bool,
 *     isApprover?: bool,
 *     needIdentificationDocuments: bool,
 *     identificationDocumentsWaitingApproval: bool,
 * }
 *
 * Request input contracts
 *
 * @psalm-type LibresignFolderSettings = array{
 *     folderName?: string,
 *     path?: string,
 *     separator?: string,
 *     folderPatterns?: array{
 *         name: string,
 *         setting?: string,
 *     },
 *     envelopeFolderId?: int,
 * }
 * @psalm-type LibresignNewSigner = array{
 *     identifyMethods: list<array{
 *         method: string,
 *         value: string,
 *         mandatory: non-negative-int,
 *     }>,
 *     displayName?: string,
 *     description?: string,
 *     notify?: non-negative-int,
 *     signingOrder?: non-negative-int,
 *     status?: int,
 * }
 * @psalm-type LibresignNewFile = array{
 *     base64?: string,
 *     nodeId?: non-negative-int,
 *     path?: string,
 *     url?: string,
 * }
 * @psalm-type LibresignIdDocs = array{
 *     file: LibresignNewFile,
 *     name?: string,
 *     type?: string,
 * }
 *
 * Identity and signer contracts
 *
 * @psalm-type LibresignIdentifyMethod = array{
 *     method: 'account'|'email'|'signal'|'sms'|'telegram'|'whatsapp'|'xmpp',
 *     value: string,
 *     mandatory: non-negative-int,
 * }
 * @psalm-type LibresignCoordinate = array{
 *     page?: int,
 *     urx?: int,
 *     ury?: int,
 *     llx?: int,
 *     lly?: int,
 *     top?: int,
 *     left?: int,
 *     width?: int,
 *     height?: int,
 * }
 * @psalm-type LibresignVisibleElement = array{
 *     elementId: int,
 *     signRequestId: int,
 *     fileId: int,
 *     type: string,
 *     coordinates: LibresignCoordinate,
 * }
 * @psalm-type LibresignSignatureMethod = array{
 *     enabled: bool,
 *     label: string,
 *     name: string,
 * }
 * @psalm-type LibresignSignatureMethodEmailToken = array{
 *     label: string,
 *     identifyMethod: "email"|"account",
 *     needCode: bool,
 *     hasConfirmCode: bool,
 *     blurredEmail: string,
 *     hashOfEmail: string,
 * }
 * @psalm-type LibresignSignatureMethodPassword = array{
 *     label: string,
 *     name: string,
 *     hasSignatureFile: bool,
 * }
 * @psalm-type LibresignSignatureMethods = array{
 *     clickToSign?: LibresignSignatureMethod,
 *     emailToken?: LibresignSignatureMethodEmailToken,
 *     password?: LibresignSignatureMethodPassword,
 * }
 * @psalm-type LibresignIdentifyMethodSetting = array{
 *     name: string,
 *     friendly_name: string,
 *     enabled: bool,
 *     mandatory: bool,
 *     signatureMethods?: LibresignSignatureMethods,
 * }
 * @psalm-type LibresignIdentifyAccount = array{
 *     identify: string,
 *     isNoUser: boolean,
 *     displayName: string,
 *     subname: string,
 *     shareType: 0|4,
 *     method?: 'account'|'email'|'signal'|'sms'|'telegram'|'whatsapp'|'xmpp',
 *     iconName?: 'account'|'email'|'signal'|'sms'|'telegram'|'whatsapp'|'xmpp',
 *     acceptsEmailNotifications?: boolean,
 * }
 * @psalm-type LibresignIdentifyAccountsResponse = list<LibresignIdentifyAccount>
 * @psalm-type LibresignNotify = array{
 *     date: non-negative-int,
 *     method: "activity"|"notify"|"mail",
 *     description?: string,
 * }
 * @psalm-type LibresignRequestedBy = array{
 *     userId: string,
 *     displayName: ?string,
 * }
 * @psalm-type LibresignDynamicMetadataScalar = string|int|float|bool|null
 * @psalm-type LibresignDynamicMetadataRecord = array<string, LibresignDynamicMetadataScalar>
 * @psalm-type LibresignDynamicMetadataValue = LibresignDynamicMetadataScalar|list<LibresignDynamicMetadataScalar>|LibresignDynamicMetadataRecord|list<LibresignDynamicMetadataRecord>
 * @psalm-type LibresignSignerCertificateInfo = array{
 *     serialNumber?: string,
 *     serialNumberHex?: string,
 *     hash?: string,
 *     subject?: LibresignDynamicMetadataValue,
 * }
 * @psalm-type LibresignSignerMetadata = array{
 *     remote-address?: string,
 *     user-agent?: string,
 *     notify?: LibresignNotify[],
 *     certificate_info?: LibresignSignerCertificateInfo,
 * }
 * @psalm-type LibresignSignerSummary = array{
 *     signRequestId: int,
 *     displayName: string,
 *     email?: ?string,
 *     identifyMethods?: LibresignIdentifyMethod[],
 *     signed: ?string,
 *     status: int,
 *     statusText: string,
 * }
 * @psalm-type LibresignSignerDetail = LibresignSignerSummary&array{
 *     description: ?string,
 *     subject?: string,
 *     request_sign_date: string,
 *     valid_from?: non-negative-int,
 *     valid_to?: non-negative-int,
 *     remote_address?: string,
 *     user_agent?: string,
 *     notify?: LibresignNotify[],
 *     userId?: string,
 *     sign_date?: ?string,
 *     sign_request_uuid?: string,
 *     hash_algorithm?: string,
 *     me: bool,
 *     status: 0|1|2,
 *     signingOrder?: non-negative-int,
 *     visibleElements: LibresignVisibleElement[],
 *     signatureMethods?: LibresignSignatureMethods,
 *     uid?: string,
 *     metadata?: LibresignSignerMetadata,
 * }
 *
 * Shared feedback and action contracts
 *
 * @psalm-type LibresignInfoMessage = array{
 *     type: 'info',
 *     message: string,
 * }
 * @psalm-type LibresignErrorItem = array{
 *     message: string,
 *     title?: string,
 * }
 * @psalm-type LibresignErrorsResponse = array{
 *     errors: list<LibresignErrorItem>,
 * }
 * @psalm-type LibresignMessageResponse = array{
 *     message: string,
 * }
 * @psalm-type LibresignMessagesResponse = array{
 *     messages: list<string>,
 * }
 * @psalm-type LibresignErrorResponse = array{
 *     error: string,
 * }
 * @psalm-type LibresignDangerMessage = array{
 *     type: 'danger',
 *     message: string,
 * }
 * @psalm-type LibresignDangerMessagesResponse = array{
 *     messages: list<LibresignDangerMessage>,
 * }
 * @psalm-type LibresignActionErrorWithCode = LibresignErrorItem&array{
 *     code?: int,
 * }
 * @psalm-type LibresignStatusMessageResponse = array{
 *     message: string,
 *     status: string,
 * }
 * @psalm-type LibresignSuccessStatusResponse = array{
 *     status: 'success',
 * }
 * @psalm-type LibresignFailureStatusResponse = array{
 *     status: 'failure',
 *     message: string,
 * }
 * @psalm-type LibresignErrorStatusResponse = array{
 *     status: 'error',
 *     message: string,
 * }
 * @psalm-type LibresignActionErrorResponse = array{
 *     action: int,
 *     errors: list<LibresignErrorItem>,
 *     messages?: list<LibresignInfoMessage>,
 *     message?: string,
 * }
 * @psalm-type LibresignActionMessageResponse = array{
 *     action: int,
 *     message: string,
 * }
 * @psalm-type LibresignFileUuidReference = array{
 *     uuid: string,
 * }
 * @psalm-type LibresignSigningJob = array{
 *     status: 'SIGNING_IN_PROGRESS',
 *     file: LibresignFileUuidReference,
 * }
 * @psalm-type LibresignSignActionResponse = array{
 *     action: int,
 *     message?: string,
 *     file?: LibresignFileUuidReference,
 *     job?: LibresignSigningJob,
 * }
 * @psalm-type LibresignSignActionErrorResponse = array{
 *     action: int,
 *     errors: list<LibresignActionErrorWithCode>,
 *     redirect?: string,
 * }
 *
 * Certificate and admin contracts
 *
 * @psalm-type LibresignConfigureCheck = array{
 *     message: string,
 *     resource: string,
 *     status: "error"|"success",
 *     tip: string,
 * }
 * @psalm-type LibresignConfigureChecksResponse = list<LibresignConfigureCheck>
 * @psalm-type LibresignCertificatePfxData = array{
 *     name: string,
 *     subject: string,
 *     issuer: string,
 *     extensions: string,
 *     serialNumber: string,
 *     serialNumberHex: string,
 *     validate: array{
 *         from: string,
 *         to: string,
 *     },
 * }
 * @psalm-type LibresignRootCertificateName = array{
 *     id: string,
 *     value: string,
 * }
 * @psalm-type LibresignRootCertificate = array{
 *     commonName: string,
 *     names: LibresignRootCertificateName[],
 * }
 * @psalm-type LibresignPolicySection = array{
 *      OID: string,
 *      CPS: string,
 *  }
 * @psalm-type LibresignEngineHandler = array{
 *     configPath: string,
 *     cfsslUri?: string,
 *     policySection: LibresignPolicySection[],
 *     rootCert: LibresignRootCertificate,
 * }
 * @psalm-type LibresignCertificateDataGenerated = LibresignEngineHandler&array{
 *     generated: boolean,
 * }
 * @psalm-type LibresignEngineHandlerResponse = array{
 *     data: LibresignEngineHandler,
 * }
 * @psalm-type LibresignCertificateEngineConfigResponse = array{
 *     engine: string,
 *     identify_methods: list<LibresignIdentifyMethodSetting>,
 * }
 * @psalm-type LibresignHasRootCertResponse = array{
 *     hasRootCert: bool,
 * }
 * @psalm-type LibresignReminderSettings = array{
 *     days_before: non-negative-int,
 *     days_between: non-negative-int,
 *     max: non-negative-int,
 *     send_timer: string,
 *     next_run?: string,
 * }
 * @psalm-type LibresignAdminSigningMode = 'sync'|'async'
 * @psalm-type LibresignAdminWorkerType = 'local'|'external'
 * @psalm-type LibresignAdminSignatureEngine = 'JSignPdf'|'PhpNative'
 * @psalm-type LibresignDocMdpLevelOption = array{
 *     value: int,
 *     label: string,
 *     description: string,
 * }
 * @psalm-type LibresignDocMdpConfig = array{
 *     enabled: bool,
 *     defaultLevel: int,
 *     availableLevels: list<LibresignDocMdpLevelOption>,
 * }
 * @psalm-type LibresignSignatureTextSettingsResponse = array{
 *     template: string,
 *     parsed: string,
 *     templateFontSize: float,
 *     signatureFontSize: float,
 *     signatureWidth: float,
 *     signatureHeight: float,
 *     renderMode: string,
 * }
 * @psalm-type LibresignSignatureTemplateSettingsResponse = array{
 *     default_signature_text_template: string,
 *     signature_available_variables: array<string, string>,
 * }
 * @psalm-type LibresignCertificatePolicyResponse = array{
 *     status: 'success',
 *     CPS: string,
 * }
 * @psalm-type LibresignFooterTemplateResponse = array{
 *     template: string,
 *     isDefault: bool,
 *     preview_width: int,
 *     preview_height: int,
 * }
 * @psalm-type LibresignActiveSigningItem = array{
 *     id: int,
 *     uuid: string,
 *     name: string,
 *     signerEmail: string,
 *     signerDisplayName: string,
 *     updatedAt: int,
 * }
 * @psalm-type LibresignActiveSigningsResponse = array{
 *     data: list<LibresignActiveSigningItem>,
 * }
 *
 * Validation and progress contracts
 *
 * @psalm-type LibresignEffectivePolicyValue = null|bool|int|float|string
 * @psalm-type LibresignEffectivePolicyState = array{
 *     policyKey: string,
 *     effectiveValue: LibresignEffectivePolicyValue,
 *     sourceScope: string,
 *     visible: bool,
 *     editableByCurrentActor: bool,
 *     allowedValues: list<LibresignEffectivePolicyValue>,
 *     canSaveAsUserDefault: bool,
 *     canUseAsRequestOverride: bool,
 *     preferenceWasCleared: bool,
 *     blockedBy: ?string,
 *     groupCount: non-negative-int,
 *     userCount: non-negative-int,
 * }
 * @psalm-type LibresignEffectivePolicyResponse = array{
 *     policy: LibresignEffectivePolicyState,
 * }
 * @psalm-type LibresignEffectivePoliciesResponse = array{
 *     policies: array<string, LibresignEffectivePolicyState>,
 * }
 * @psalm-type LibresignSystemPolicyWriteRequest = array{
 *     value: LibresignEffectivePolicyValue,
 * }
 * @psalm-type LibresignGroupPolicyState = array{
 *     policyKey: string,
 *     scope: 'group',
 *     targetId: string,
 *     value: null|LibresignEffectivePolicyValue,
 *     allowChildOverride: bool,
 *     visibleToChild: bool,
 *     allowedValues: list<LibresignEffectivePolicyValue>,
 * }
 * @psalm-type LibresignGroupPolicyResponse = array{
 *     policy: LibresignGroupPolicyState,
 * }
 * @psalm-type LibresignGroupPolicyWriteRequest = array{
 *     value: LibresignEffectivePolicyValue,
 *     allowChildOverride: bool,
 * }
 * @psalm-type LibresignSystemPolicyState = array{
 *     policyKey: string,
 *     scope: 'system'|'global',
 *     value: null|LibresignEffectivePolicyValue,
 *     allowChildOverride: bool,
 *     visibleToChild: bool,
 *     allowedValues: list<LibresignEffectivePolicyValue>,
 * }
 * @psalm-type LibresignSystemPolicyResponse = array{
 *     policy: LibresignSystemPolicyState,
 * }
 * @psalm-type LibresignUserPolicyState = array{
 *     policyKey: string,
 *     scope: 'user_policy',
 *     targetId: string,
 *     value: null|LibresignEffectivePolicyValue,
 *     allowChildOverride: bool,
 * }
 * @psalm-type LibresignUserPolicyResponse = array{
 *     policy: LibresignUserPolicyState,
 * }
 * @psalm-type LibresignGroupPolicyWriteResponse = LibresignMessageResponse&LibresignGroupPolicyResponse
 * @psalm-type LibresignSystemPolicyWriteResponse = LibresignMessageResponse&LibresignEffectivePolicyResponse
 * @psalm-type LibresignUserPolicyWriteResponse = LibresignMessageResponse&LibresignUserPolicyResponse
 * @psalm-type LibresignPolicySnapshotEntry = array{
 *     effectiveValue: string,
 *     sourceScope: string,
 * }
 * @psalm-type LibresignPolicySnapshotNumericEntry = array{
 *     effectiveValue: int,
 *     sourceScope: string,
 * }
 * @psalm-type LibresignValidatePolicySnapshot = array{
 *     docmdp?: LibresignPolicySnapshotNumericEntry,
 *     signature_flow?: LibresignPolicySnapshotEntry,
 *     add_footer?: LibresignPolicySnapshotEntry,
 * }
 * @psalm-type LibresignValidateMetadata = array{
 *     extension: string,
 *     p: int,
 *     d?: list<array{w: float, h: float}>,
 *     original_file_deleted?: bool,
 *     policy_snapshot?: LibresignValidatePolicySnapshot,
 *     pdfVersion?: string,
 *     status_changed_at?: string,
 * }
 * @psalm-type LibresignFileRuntimeMetadata = LibresignValidateMetadata|array<string, LibresignDynamicMetadataValue>
 * @psalm-type LibresignValidationPageResolution = array{
 *     w: float,
 *     h: float,
 * }
 * @psalm-type LibresignValidationPage = array{
 *     number: int,
 *     url: string,
 *     resolution: LibresignValidationPageResolution,
 * }
 * @psalm-type LibresignValidatedChildFile = array{
 *     id: int,
 *     uuid: string,
 *     name: string,
 *     status: int,
 *     statusText: string,
 *     nodeId: int,
 *     totalPages?: non-negative-int,
 *     size: non-negative-int,
 *     pdfVersion?: string,
 *     signers: list<LibresignSignerSummary>,
 *     file: string,
 *     metadata: LibresignValidateMetadata,
 * }
 * @psalm-type LibresignValidatedFile = array{
 *     id: int,
 *     uuid: string,
 *     name: string,
 *     status: 0|1|2|3|4,
 *     statusText: string,
 *     nodeId: non-negative-int,
 *     nodeType: 'file'|'envelope',
 *     signatureFlow: 'none'|'parallel'|'ordered_numeric',
 *     docmdpLevel: int,
 *     filesCount: int<0, max>,
 *     files: list<LibresignValidatedChildFile>,
 *     totalPages: non-negative-int,
 *     size: non-negative-int,
 *     pdfVersion: string,
 *     created_at: string,
 *     requested_by: LibresignRequestedBy,
 *     file?: string,
 *     url?: string,
 *     mime?: string,
 *     pages?: list<LibresignValidationPage>,
 *     metadata?: LibresignValidateMetadata,
 *     signers?: LibresignSignerDetail[],
 *     signersCount?: int<0, max>,
 *     settings?: LibresignSettings,
 *     messages?: list<LibresignInfoMessage>,
 *     visibleElements?: LibresignVisibleElement[],
 * }
 * @psalm-type LibresignProgressError = array{
 *     message: string,
 *     code?: int,
 *     timestamp?: string,
 *     fileId?: int,
 *     signRequestId?: int,
 *     signRequestUuid?: string,
 * }
 * @psalm-type LibresignProgressFile = array{
 *     id: int,
 *     name: string,
 *     status: int,
 *     statusText: string,
 *     error?: LibresignProgressError,
 * }
 * @psalm-type LibresignProgressSigner = array{
 *     id: int,
 *     displayName: string,
 *     signed: ?string,
 *     status: int,
 * }
 * @psalm-type LibresignProgressPayload = array{
 *     total: int,
 *     signed: int,
 *     inProgress: int,
 *     pending: int,
 *     errors?: int,
 *     files?: list<LibresignProgressFile>,
 *     signers?: list<LibresignProgressSigner>,
 * }
 * @psalm-type LibresignProgressResponse = array{
 *     status: 'NOT_LIBRESIGN_FILE'|'DRAFT'|'ABLE_TO_SIGN'|'PARTIAL_SIGNED'|'SIGNED'|'DELETED'|'SIGNING_IN_PROGRESS'|'ERROR'|'UNKNOWN',
 *     statusCode: int,
 *     statusText: string,
 *     fileId: int,
 *     progress: LibresignProgressPayload,
 *     file?: LibresignValidatedFile,
 *     error?: LibresignProgressError,
 * }
 *
 * File and listing contracts
 *
 * @psalm-type LibresignFileSummary = array{
 *     id: int,
 *     nodeId: ?int,
 *     uuid: string,
 *     name: non-falsy-string,
 *     status: int,
 *     statusText: string,
 *     nodeType: 'file'|'envelope',
 *     created_at: string,
 *     metadata: LibresignFileRuntimeMetadata,
 *     docmdpLevel: int,
 *     signatureFlow: 'none'|'parallel'|'ordered_numeric',
 *     signersCount: int,
 *     signers: list<LibresignSignerSummary>,
 *     requested_by: LibresignRequestedBy,
 *     filesCount: int<0, max>,
 *     canSign: bool,
 * }
 * @psalm-type LibresignFileListItem = array{
 *     fileId: int,
 *     id: int,
 *     nodeId: ?int,
 *     uuid: string,
 *     name: non-falsy-string,
 *     status: int,
 *     statusText: string,
 *     docmdpLevel: int,
 *     signersCount: int,
 *     file: string,
 *     metadata: LibresignFileRuntimeMetadata,
 *     size: non-negative-int,
 *     signers: list<LibresignSignerSummary>,
 * }
 * @psalm-type LibresignDetailedFile = array{
 *     created_at: string,
 *     files: list<LibresignFileListItem>,
 *     filesCount: int<0, max>,
 *     id: int,
 *     nodeId: int,
 *     uuid: string,
 *     name: string,
 *     status: int,
 *     statusText: string,
 *     nodeType: 'file'|'envelope',
 *     metadata: LibresignFileRuntimeMetadata,
 *     size: non-negative-int,
 *     docmdpLevel: int,
 *     signatureFlow: 'none'|'parallel'|'ordered_numeric',
 *     visibleElements: LibresignVisibleElement[],
 *     signers: LibresignSignerDetail[],
 *     signersCount: int,
 *     requested_by: LibresignRequestedBy,
 * }
 * @psalm-type LibresignDetailedFileResponse = LibresignDetailedFile&array{
 *     message: string,
 *     name: non-falsy-string,
 *     nodeType: 'file'|'envelope',
 *     metadata: LibresignFileRuntimeMetadata,
 *     signatureFlow: 'none'|'parallel'|'ordered_numeric',
 * }
 * @psalm-type LibresignFileListResponse = array{
 *     pagination: LibresignPagination,
 *     data: list<LibresignFileSummary|LibresignDetailedFile>,
 *     settings?: LibresignSettings,
 * }
 *
 * Account, elements, and ID document contracts
 *
 * @psalm-type LibresignFile = array{
 *     account: array{
 *         userId: string,
 *         displayName: string,
 *     },
 *     file_type: array{
 *         type: string,
 *         name: string,
 *         description: ?string,
 *     },
 *     created_at: string,
 *     file: array{
 *         name: string,
 *         status: 0|1|2|3|4,
 *         statusText: string,
 *         created_at: string,
 *         file: array{
 *             type: string,
 *             nodeId: non-negative-int,
 *             signedNodeId: non-negative-int,
 *             url: string,
 *         },
 *         callback: ?string,
 *         uuid: string,
 *         signers: LibresignSignerDetail[],
 *     },
 * }
 * @psalm-type LibresignFileElementIdResponse = array{
 *     fileElementId: int,
 * }
 * @psalm-type LibresignUserElement = array{
 *     id: int,
 *     type: string,
 *     file: array{
 *         url: string,
 *         nodeId: int,
 *     },
 *     userId: string,
 *     starred: bool,
 *     createdAt: string,
 * }
 * @psalm-type LibresignUserElementsResponse = array{
 *     elements: LibresignUserElement[],
 * }
 * @psalm-type LibresignUserElementsMessageResponse = array{
 *     elements: LibresignUserElement[],
 *     message: string,
 * }
 * @psalm-type LibresignAccountMeResponse = array{
 *     account: array{
 *         uid: string,
 *         emailAddress: string,
 *         displayName: string,
 *     },
 *     settings: array{
 *         canRequestSign: bool,
 *         hasSignatureFile: bool,
 *         phoneNumber: string,
 *     },
 * }
 * @psalm-type LibresignAccountSettingsUpdateResponse = array{
 *     data: array{
 *         userId: string,
 *         phone: string,
 *         message: string,
 *     },
 * }
 * @psalm-type LibresignConfigValueResponse = array{
 *     key: string,
 *     value: ?string,
 * }
 * @psalm-type LibresignIdDocsUploadErrorResponse = array{
 *     file: ?int,
 *     type: 'info'|'warning'|'danger'|null,
 *     message: string,
 * }
 * @psalm-type LibresignIdDocsListResponse = array{
 *     pagination: LibresignPagination,
 *     data: list<LibresignFile>,
 * }
 * @psalm-type LibresignIdDocsApprovalListResponse = array{
 *     pagination: LibresignPagination,
 *     data: list<LibresignFile>,
 * }
 * @psalm-type LibresignCreateToSignPdfReference = array{
 *     url: string,
 * }
 * @psalm-type LibresignCreateToSignResponse = array{
 *     action: 2000|2500,
 *     message: string,
 *     description?: null|string,
 *     filename?: string,
 *     pdf?: LibresignCreateToSignPdfReference,
 * }
 *
 * CRL contracts
 *
 * @psalm-type LibresignCrlErrorResponse = array{
 *     error: string,
 *     message: string,
 * }
 * @psalm-type LibresignCrlCertificateStatusResponse = array{
 *     serial_number: string,
 *     status: 'valid'|'revoked'|'expired'|'unknown',
 *     checked_at: string,
 *     reason_code?: null|int,
 *     revoked_at?: string,
 *     valid_to?: string,
 * }
 * @psalm-type LibresignCrlListItem = array{
 *     id: int,
 *     serial_number: string,
 *     owner: string,
 *     status: 'issued'|'revoked',
 *     certificate_type: string,
 *     engine: string,
 *     instance_id: ?string,
 *     generation: ?int,
 *     issued_at: ?string,
 *     valid_to: ?string,
 *     revoked_at: ?string,
 *     reason_code: ?int,
 *     comment: ?string,
 *     revoked_by: ?string,
 *     invalidity_date: ?string,
 *     crl_number: ?int,
 * }
 * @psalm-type LibresignCrlListResponse = array{
 *     data: list<LibresignCrlListItem>,
 *     total: int,
 *     page: int,
 *     length: int,
 * }
 * @psalm-type LibresignCrlRevokeResponse = array{
 *     success: bool,
 *     message: string,
 * }
 *
 * Capabilities contracts
 *
 * @psalm-type LibresignCapabilities = array{
 *     features: list<string>,
 *     config: array{
 *         show-confetti: bool,
 *         sign-elements: array{
 *             is-available: bool,
 *             can-create-signature: bool,
 *             full-signature-width: float,
 *             full-signature-height: float,
 *             signature-width: float,
 *             signature-height: float,
 *         },
 *         envelope: array{
 *             is-available: bool,
 *         },
 *         upload: array{
 *             max-file-uploads: int,
 *         },
 *     },
 *     version: string,
 * }
 */
class ResponseDefinitions {
}
