<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign;

/**
 * Psalm-only type aliases that are not part of the generated OpenAPI contract.
 *
 * Keep OpenAPI ResponseDefinitions limited to schemas reachable from documented
 * controller operations so openapi-extractor v1.9+ does not report unused schemas.
 *
 * @psalm-type LibresignAccountCapabilitySettings = array{
 *     canRequestSign: bool,
 *     hasSignatureFile: bool,
 *     isApprover: bool,
 * }
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
 */
class InternalTypeAliases {
}
