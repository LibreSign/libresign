<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign;

/**
 * @psalm-type LibresignCoordinate = array{
 *     page: int,
 *     urx?: int,
 *     ury?: int,
 *     llx?: int,
 *     lly?: int,
 *     top?: int,
 *     left?: int,
 *     width?: int,
 *     height?: int,
 *     page?: int,
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
 *         status: string,
 *         statusText: string,
 *         request_date: string,
 *         file: array{
 *             type: string,
 *             nodeId: int,
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
 *     }
 * }
 * @psalm-type LibresignIdentifyMethod = array{
 *     method: string,
 *     value: string,
 *     mandatory: int,
 * }
 * @psalm-type LibresignNewFile = array{
 *     base64?: string,
 *     fileId?: int,
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
 * @psalm-type LibresignPagination = array{
 *     total: int,
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
 *     email?: string,
 *     description: ?string,
 *     displayName: string,
 *     request_sign_date: string,
 *     signed: ?string,
 *     sign_date?: ?string,
 *     sign_uuid?: string,
 *     me: bool,
 *     uid?: string,
 *     signRequestId: int,
 *     identifyMethod?: string,
 *     identifyMethods?: LibresignIdentifyMethod[],
 *     visibleElements?: LibresignVisibleElement[],
 * }
 * @psalm-type LibresignVisibleElement = array{
 *     elementId: int,
 *     signRequestId: int,
 *     type: string,
 *     coordinates: LibresignCoordinate,
 * }
 */
class ResponseDefinitions {
}
