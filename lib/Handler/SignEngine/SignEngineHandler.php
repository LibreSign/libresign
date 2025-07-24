<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCP\Files\File;
use TypeError;

abstract class SignEngineHandler implements ISignEngineHandler {
	private File $inputFile;
	protected string $certificate;
	private string $password = '';
	/** @var VisibleElementAssoc[] */
	private array $visibleElements = [];
	private array $signatureParams = [];

	/**
	 * @return static
	 */
	public function setInputFile(File $inputFile): self {
		$this->inputFile = $inputFile;
		return $this;
	}

	public function getInputFile(): File {
		return $this->inputFile;
	}

	public function setCertificate(string $certificate): self {
		$this->certificate = $certificate;
		return $this;
	}

	public function getCertificate(): string {
		return $this->certificate;
	}

	public function readCertificate(): array {
		return $this->getCertificateEngine()
			->readCertificate(
				$this->getCertificate(),
				$this->getPassword()
			);
	}

	public function setPassword(string $password): self {
		$this->password = $password;
		return $this;
	}

	public function getPassword(): string {
		return $this->password;
	}

	/**
	 * @param VisibleElementAssoc[] $visibleElements
	 *
	 * @return static
	 */
	public function setVisibleElements(array $visibleElements): self {
		$this->visibleElements = $visibleElements;
		return $this;
	}

	/**
	 * @return VisibleElementAssoc[]
	 *
	 * @psalm-return array<VisibleElementAssoc>
	 */
	public function getVisibleElements(): array {
		return $this->visibleElements;
	}

	public function getSignedContent(): string {
		return $this->sign()->getContent();
	}

	public function getSignatureParams(): array {
		return $this->signatureParams;
	}

	public function setSignatureParams(array $params): self {
		$this->signatureParams = $params;
		return $this;
	}

	/**
	 * Generate certificate
	 *
	 * @param array $user Example: ['host' => '', 'name' => '']
	 * @param string $signPassword Password of signature
	 * @param string $friendlyName Friendly name
	 */
	public function generateCertificate(array $user, string $signPassword, string $friendlyName): string {
		$content = $this->getCertificateEngine()
			->setHosts([$user['host']])
			->setCommonName($user['name'])
			->setFriendlyName($friendlyName)
			->setUID($user['uid'])
			->setPassword($signPassword)
			->generateCertificate();
		if (!$content) {
			throw new TypeError();
		}
		return $content;
	}

	private function getCertificateEngine(): IEngineHandler {
		return \OCP\Server::get(CertificateEngineFactory::class)
			->getEngine();
	}
}
