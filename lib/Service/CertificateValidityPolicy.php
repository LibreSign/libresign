<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\ISignatureMethod;

class CertificateValidityPolicy {
	private const CLICK_TO_SIGN_CERT_VALIDITY_DAYS = 1;

	public function getLeafExpiryDays(?string $signatureMethodName, bool $signWithoutPassword): ?int {
		if (!$signWithoutPassword) {
			return null;
		}
		if ($signatureMethodName === ISignatureMethod::SIGNATURE_METHOD_CLICK_TO_SIGN) {
			return self::CLICK_TO_SIGN_CERT_VALIDITY_DAYS;
		}
		return null;
	}
}
