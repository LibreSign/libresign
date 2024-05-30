<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign;

/**
 * @psalm-type LibresignConfigureCheck = array{
 *     status: "error"|"success",
 *     message: string,
 *     resource: string,
 *     tip: string,
 * }
 * @psalm-type LibresignCoordinate = array{
 *     page: non-negative-int,
 *     urx?: non-negative-int,
 *     ury?: non-negative-int,
 *     llx?: non-negative-int,
 *     lly?: non-negative-int,
 *     top?: non-negative-int,
 *     left?: non-negative-int,
 *     width?: non-negative-int,
 *     height?: non-negative-int,
 * }
 * @psalm-type LibresignRequestSignature = array{
 *     file: string,
 *     name: string,
 *     nodeId: non-negative-int,
 *     request_date: string,
 *     requested_by: array{
 *         uid: string,
 *         displayName: string,
 *     },
 *     signers: LibresignSigner[],
 *     status: 0|1|2,
 *     statusText: string,
 *     uuid: string,
 *     settings: LibresignSettings,
 * }
 * @psalm-type LibresignFile = array{
 *     account: array{
 *         uid: string,
 *         displayName: string,
 *     },
 *     file_type: array{
 *         type: string,
 *         name: string,
 *         description: ?string,
 *     },
 *     request_date: string,
 *     file: array{
 *         name: string,
 *         status: 0|1|2|3|4,
 *         statusText: string,
 *         request_date: string,
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
 * @psalm-type LibresignFolderSettings = array{
 *     folderName?: string,
 *     separator?: string,
 *     folderPatterns?: array{
 *         name: string,
 *         setting?: string,
 *     },
 * }
 * @psalm-type LibresignIdentifyMethod = array{
 *     method: "email"|"account",
 *     value: string,
 *     mandatory: non-negative-int,
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
 *     id: non-negative-int,
 *     etag: string,
 *     path: string,
 *     type: string,
 * }
 * @psalm-type LibresignPagination = array{
 *     total: non-negative-int,
 *     current: ?string,
 *     next: ?string,
 *     prev: ?string,
 *     last: ?string,
 *     first: ?string,
 * }
 * @psalm-type LibresignRootCertificateName = array{
 *     id: string,
 *     value: string,
 * }
 * @psalm-type LibresignRootCertificate = array{
 *     commonName: string,
 *     names: LibresignRootCertificateName[],
 *     name?: string,
 *     type?: string,
 * }
 * @psalm-type LibresignSettings = array{
 *     canSign: bool,
 *     canRequestSign: bool,
 *     signerFileUuid: ?string,
 *     hasSignatureFile?: bool,
 *     phoneNumber: string,
 * }
 * @psalm-type LibresignSigner = array{
 *     description: ?string,
 *     displayName: string,
 *     request_sign_date: string,
 *     signed: ?string,
 *     sign_date?: ?string,
 *     sign_uuid?: string,
 *     me: bool,
 *     signRequestId: non-negative-int,
 *     identifyMethods?: LibresignIdentifyMethod[],
 *     visibleElements?: LibresignVisibleElement[],
 * }
 * @psalm-type LibresignVisibleElement = array{
 *     elementId: non-negative-int,
 *     signRequestId: non-negative-int,
 *     type: string,
 *     coordinates: LibresignCoordinate,
 * }
 */
class ResponseDefinitions {
}
