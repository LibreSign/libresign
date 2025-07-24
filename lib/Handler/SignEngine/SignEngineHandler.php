<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Service\FolderService;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use OCP\IL10N;
use TypeError;

abstract class SignEngineHandler implements ISignEngineHandler {
	private File $inputFile;
	protected string $certificate = '';
	private string $pfxFilename = 'signature.pfx';
	private string $password = '';
	/** @var VisibleElementAssoc[] */
	private array $visibleElements = [];
	private array $signatureParams = [];

	public function __construct(
		private IL10N $l10n,
		private readonly FolderService $folderService,
	) {
	}

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

	public function savePfx(string $uid, string $content): string {
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		if ($folder->nodeExists($this->pfxFilename)) {
			$file = $folder->get($this->pfxFilename);
			if (!$file instanceof File) {
				throw new LibresignException("path {$this->pfxFilename} already exists and is not a file!", 400);
			}
			try {
				$file->putContent($content);
			} catch (GenericFileException) {
				throw new LibresignException("path {$file->getPath()} does not exists!", 400);
			}
			return $content;
		}

		$file = $folder->newFile($this->pfxFilename);
		$file->putContent($content);
		return $content;
	}

	public function deletePfx(string $uid): void {
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		try {
			$file = $folder->get($this->pfxFilename);
			$file->delete();
		} catch (\Throwable) {
		}
	}

	/**
	 * Get content of pfx file
	 */
	public function getPfxOfCurrentSigner(?string $uid = null): string {
		if (!empty($this->certificate) || empty($uid)) {
			return $this->certificate;
		}
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		if (!$folder->nodeExists($this->pfxFilename)) {
			throw new LibresignException($this->l10n->t('Password to sign not defined. Create a password to sign.'), 400);
		}
		try {
			/** @var \OCP\Files\File */
			$node = $folder->get($this->pfxFilename);
			$this->certificate = $node->getContent();
		} catch (GenericFileException) {
			throw new LibresignException($this->l10n->t('Password to sign not defined. Create a password to sign.'), 400);
		} catch (\Throwable) {
		}
		if (empty($this->certificate)) {
			throw new LibresignException($this->l10n->t('Password to sign not defined. Create a password to sign.'), 400);
		}
		if ($this->getPassword()) {
			try {
				$this->getCertificateEngine()->readCertificate($this->certificate, $this->getPassword());
			} catch (InvalidPasswordException) {
				throw new LibresignException($this->l10n->t('Invalid password'));
			}
		}
		return $this->certificate;
	}

	public function updatePassword(string $uid, string $currentPrivateKey, string $newPrivateKey): string {
		$pfx = $this->getPfxOfCurrentSigner($uid);
		$content = $this->getCertificateEngine()->updatePassword(
			$pfx,
			$currentPrivateKey,
			$newPrivateKey
		);
		return $this->savePfx($uid, $content);
	}

	private function getCertificateEngine(): IEngineHandler {
		return \OCP\Server::get(CertificateEngineFactory::class)
			->getEngine();
	}
}
