<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signature;

use OCA\Libresign\Vendor\LibreSign\PdfSignatureValidator\Model\ExtractedSignature;
use OCA\Libresign\Vendor\LibreSign\PdfSignatureValidator\Model\TimestampToken;
use OCA\Libresign\Vendor\LibreSign\PdfSignatureValidator\Model\ValidationResult;

/**
 * Internal PDF signature validation contracts.
 *
 * @psalm-type MappedPdfValidationResult = array{
 *     signature: ExtractedSignature,
 *     certificates: list<string>,
 *     timestamp: ?TimestampToken,
 *     signatureValidation: array,
 *     certificateValidation: array,
 *     raw: array{
 *         signature: ValidationResult,
 *         certificate: ValidationResult,
 *     },
 * }
 */
final class PdfSignatureValidationDefinitions {
}
