<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

/** Prepare a validated, immutable CSC publication without a database commit. */
interface PublicationPreparation {
	public function prepare(
		AuthorizedRequest $authorized,
		string $sessionId,
		#[\SensitiveParameter] string $expectedCertificatePem,
	): PublicationContext;
}
