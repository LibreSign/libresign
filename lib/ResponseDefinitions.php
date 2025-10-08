<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign;

/**
 * @psalm-type LibresignConfigureCheck = array{
 *     message: string,
 *     resource: string,
 *     status: "error"|"success",
 *     tip: string,
 * }
 * @psalm-type LibresignFolderSettings = array{
 *     folderName?: string,
 *     separator?: string,
 *     folderPatterns?: array{
 *         name: string,
 *         setting?: string,
 *     },
 * }
 * @psalm-type LibresignNewSigner = array{
 *     identify: array{
 *         email?: string,
 *         account?: string,
 *     },
 * }
 * @psalm-type LibresignNewFile = array{
 *     base64?: string,
 *     fileId?: non-negative-int,
 *     url?: string,
 * }
 * @psalm-type LibresignAccountFile = array{
 *     file: LibresignNewFile,
 *     name?: string,
 *     type?: string,
 * }
 * @psalm-type LibresignNextcloudFile = array{
 *     message: string,
 *     name: string,
 *     id: int,
 *     etag: string,
 *     path: string,
 *     type: string,
 * }
 * @psalm-type LibresignIdentifyAccount = array{
 *     id: non-negative-int,
 *     isNoUser: boolean,
 *     displayName: string,
 *     subname: string,
 *     shareType: 0|4,
 *     icon?: 'icon-mail'|'icon-user',
 * }
 * @psalm-type LibresignPagination = array{
 *     total: non-negative-int,
 *     current: ?string,
 *     next: ?string,
 *     prev: ?string,
 *     last: ?string,
 *     first: ?string,
 * }
 * @psalm-type LibresignCertificatePfxData = array{
 *     name: string,
 *     subject: string,
 *     issuer: string,
 *     extensions: string,
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
 * @psalm-type LibresignCetificateDataGenerated = LibresignEngineHandler&array{
 *     generated: boolean,
 * }
 * @psalm-type LibresignSettings = array{
 *     canSign: bool,
 *     canRequestSign: bool,
 *     signerFileUuid: ?string,
 *     hasSignatureFile?: bool,
 *     phoneNumber: string,
 *     needIdentificationDocuments?: bool,
 *     identificationDocumentsWaitingApproval?: bool,
 * }
 * @psalm-type LibresignIdentifyMethod = array{
 *     method: "email"|"account",
 *     value: string,
 *     mandatory: non-negative-int,
 * }
 * @psalm-type LibresignCoordinate = array{
 *     page?: non-negative-int,
 *     urx?: non-negative-int,
 *     ury?: non-negative-int,
 *     llx?: non-negative-int,
 *     lly?: non-negative-int,
 *     top?: non-negative-int,
 *     left?: non-negative-int,
 *     width?: non-negative-int,
 *     height?: non-negative-int,
 * }
 * @psalm-type LibresignVisibleElement = array{
 *     elementId: non-negative-int,
 *     signRequestId: non-negative-int,
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
 * @psalm-type LibresignNotify = array{
 *     date: string,
 *     method: "activity"|"notify"|"mail",
 * }
 * @psalm-type LibresignSigner = array{
 *     description: ?string,
 *     displayName: string,
 *     subject?: string,
 *     request_sign_date: string,
 *     valid_from?: non-negative-int,
 *     valid_to?: non-negative-int,
 *     email?: string,
 *     remote_address?: string,
 *     user_agent?: string,
 *     notify?: LibresignNotify[],
 *     userId?: string,
 *     signed: ?string,
 *     sign_date?: ?string,
 *     sign_uuid?: string,
 *     hash_algorithm?: string,
 *     me: bool,
 *     signRequestId: non-negative-int,
 *     identifyMethods?: LibresignIdentifyMethod[],
 *     visibleElements?: LibresignVisibleElement[],
 *     signatureMethods?: LibresignSignatureMethods,
 * }
 * @psalm-type LibresignValidateFile = array{
 *     uuid: string,
 *     name: string,
 *     status: 0|1|2|3|4,
 *     statusText: string,
 *     nodeId: non-negative-int,
 *     totalPages: non-negative-int,
 *     size: non-negative-int,
 *     pdfVersion: string,
 *     created_at: string,
 *     requested_by: array{
 *         userId: string,
 *         displayName: string,
 *     },
 *     file: string,
 *     url?: string,
 *     signers?: LibresignSigner[],
 *     settings?: LibresignSettings,
 *     messages?: array{
 *         type: 'info',
 *         message: string,
 *     }[],
 *     visibleElements?: LibresignVisibleElement[],
 * }
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
 *             url: string,
 *         },
 *         callback: ?string,
 *         uuid: string,
 *         signers: LibresignSigner[],
 *     },
 * }
 * @psalm-type LibresignUserElement = array{
 *     id: int,
 *     type: string,
 *     file: array{
 *         url: string,
 *         nodeId: int,
 *     },
 *     userId: string,
 *     starred: 0|1,
 *     createdAt: string,
 * }
 * @psalm-type LibresignReminderSettings = array{
 *     days_before: non-negative-int,
 *     days_between: non-negative-int,
 *     max: non-negative-int,
 *     send_timer: string,
 *     next_run?: string,
 * }
 * @psalm-type LibresignCapabilities = array{
 *     features: list<string>,
 *     config: array{
 *         sign-elements: array{
 *             is-available: bool,
 *             can-create-signature: bool,
 *             full-signature-width: float,
 *             full-signature-height: float,
 *             signature-width: float,
 *             signature-height: float,
 *         },
 *     },
 *     version: string,
 * }
 */
class ResponseDefinitions {
}
