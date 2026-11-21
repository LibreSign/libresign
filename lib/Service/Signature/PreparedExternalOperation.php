<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 rodrigodev
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signature;

use SignerPHP\PdfSigner\Application\DTO\PreparedExternalSignature;

/**
 * LibreSign's envelope around one upstream prepared external-signature
 * operation.
 *
 * The envelope exists for one real responsibility: binding the upstream
 * prepared operation to the exact document bytes it was created for. The
 * upstream `PreparedExternalSignature` DTO carries no input fingerprint,
 * and its opaque state cannot be parsed without reproducing upstream
 * serialization, so complete() cannot otherwise tell whether the supplied
 * original PDF is the revision the operation was prepared for.
 *
 * The fingerprint is a comparison record for a trusted prepared operation,
 * not authentication: whoever controls the prepared operation also
 * controls the recorded value. Authenticating externally supplied state
 * requires server-side session infrastructure that is deliberately out of
 * scope here.
 */
final class PreparedExternalOperation {
	public function __construct(
		public readonly PreparedExternalSignature $upstream,
		public readonly string $preparationInputSha256,
	) {
	}
}
