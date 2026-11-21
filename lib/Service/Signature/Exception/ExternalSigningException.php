<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 rodrigodev
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signature\Exception;

/**
 * Rejected external-signing operation.
 *
 * Raised by ExternalPdfSigningService when the requested options are not
 * supported by the upstream external prepare API, or when the completed
 * document fails the post-completion validation guarantees. Callers must
 * distinguish this from upstream mechanical errors (empty signature,
 * corrupted prepared state) carried as SignerPHP exceptions.
 */
class ExternalSigningException extends \RuntimeException {
}
