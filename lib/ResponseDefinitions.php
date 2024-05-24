<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign;

/**
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
 * @psalm-type LibresignNewFile = array{
 *     file: array{
 *         fileId?: int,
 *         base64?: string,
 *     },
 *     name?: string,
 *     type?: string,
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
 * @psalm-type LibresignSigner = array{
 *     email: string,
 *     description: ?string,
 *     displayName: string,
 *     request_sign_date: string,
 *     sign_date: ?string,
 *     uid: string,
 *     signRequestId: int,
 *     identifyMethod: string,
 * }
 * @psalm-type LibresignPagination = array{
 *     total: int,
 *     current: ?string,
 *     next: ?string,
 *     prev: ?string,
 *     last: ?string,
 *     first: ?string,
 * }
 */
class ResponseDefinitions {
}
