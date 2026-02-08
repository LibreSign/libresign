<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Fixtures;

use OCA\Libresign\Handler\SignEngine\SignEngineHandler;
use OCP\Files\File;

class FakeSignEngine extends SignEngineHandler {
	public string $storedCertificate = '';
	public ?int $currentLeafExpiry = null;
	/** @var list<int|null> */
	public array $leafExpiryCalls = [];
	/** @var list<array{user: array, signPassword: string, friendlyName: string}> */
	public array $generateCalls = [];
	/** @var list<string> */
	public array $setPasswordCalls = [];
	/** @var list<string|null> */
	public array $getPfxCalls = [];
	public string $pfxToReturn = 'fake-pfx-content';
	public bool $shouldFailOnGenerate = false;

	public function __construct() {
		// No infrastructure dependencies needed
	}

	public function getCertificate(): string {
		return $this->storedCertificate;
	}

	public function setLeafExpiryOverrideInDays(?int $days): self {
		$this->currentLeafExpiry = $days;
		$this->leafExpiryCalls[] = $days;
		return $this;
	}

	public function generateCertificate(array $user, string $signPassword, string $friendlyName): string {
		if ($this->shouldFailOnGenerate) {
			throw new \RuntimeException('Certificate generation failed');
		}
		$this->generateCalls[] = [
			'user' => $user,
			'signPassword' => $signPassword,
			'friendlyName' => $friendlyName,
		];
		$this->storedCertificate = 'generated-cert';
		return $this->storedCertificate;
	}

	public function setPassword(string $password): self {
		$this->setPasswordCalls[] = $password;
		return $this;
	}

	public function getPfxOfCurrentSigner(?string $uid = null): string {
		$this->getPfxCalls[] = $uid;
		return $this->pfxToReturn;
	}

	public function sign(): File {
		throw new \LogicException('Not used by PfxProvider');
	}

	public function getCertificateChain($resource): array {
		throw new \LogicException('Not used by PfxProvider');
	}
}
