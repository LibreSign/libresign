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
	public ?string $receivedContent = null;
	/** One entry per signature found in the file, as the real handler returns */
	public array $chain = [
		['chain' => [['name' => 'first signer']]],
		['chain' => [['name' => 'second signer']]],
	];

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
		$this->receivedContent = is_resource($resource) ? stream_get_contents($resource) : null;
		return $this->chain;
	}
}
