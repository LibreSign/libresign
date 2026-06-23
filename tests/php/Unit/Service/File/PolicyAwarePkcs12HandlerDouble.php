<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;

final class PolicyAwarePkcs12HandlerDouble extends Pkcs12Handler {
	public ?string $policyUserIdForValidation = null;
	public bool $libreSignFlagSet = false;

	public function __construct() {
	}

	public function setPolicyUserIdForValidation(?string $userId): self {
		$this->policyUserIdForValidation = $userId;
		return $this;
	}

	public function setIsLibreSignFile(): void {
		$this->libreSignFlagSet = true;
	}

	public function getCertificateChain($resource): array {
		return ['chain' => []];
	}
}
