<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use InvalidArgumentException;
use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCA\Libresign\Exception\EmptyCertificateException;
use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Service\FolderService;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

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
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return static
	 */
	#[\Override]
	public function setInputFile(File $inputFile): self {
		$this->inputFile = $inputFile;
		return $this;
	}

	#[\Override]
	public function getInputFile(): File {
		return $this->inputFile;
	}

	#[\Override]
	public function setCertificate(string $certificate): self {
		$this->certificate = $certificate;
		return $this;
	}

	#[\Override]
	public function getCertificate(): string {
		return $this->certificate;
	}

	#[\Override]
	public function readCertificate(): array {
		return $this->getCertificateEngine()
			->readCertificate(
				$this->getCertificate(),
				$this->getPassword()
			);
	}

	#[\Override]
	public function setPassword(string $password): self {
		$this->password = $password;
		return $this;
	}

	#[\Override]
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

	#[\Override]
	public function getSignedContent(): string {
		return $this->sign()->getContent();
	}

	#[\Override]
	public function getSignatureParams(): array {
		return $this->signatureParams;
	}

	#[\Override]
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
		try {
			$content = $this->getCertificateEngine()
				->setHosts([$user['host']])
				->setCommonName($user['name'])
				->setFriendlyName($friendlyName)
				->setUID($user['uid'])
				->setPassword($signPassword)
				->generateCertificate();
		} catch (EmptyCertificateException) {
			throw new LibresignException($this->l10n->t('Empty root certificate data'));
		} catch (InvalidArgumentException) {
			throw new LibresignException($this->l10n->t('Invalid data to generate certificate'));
		} catch (\Throwable) {
			throw new LibresignException($this->l10n->t('Failure on generate certificate'));
		}
		if (!$content) {
			throw new LibresignException($this->l10n->t('Failure to generate certificate'));
		}
		$this->setCertificate($content);
		return $content;
	}

	public function savePfx(string $uid, string $content): string {
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();

		try {
			$folder->newFile($this->pfxFilename, $content);
		} catch (NotPermittedException) {
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		}

		return $content;
	}

	public function deletePfx(string $uid): void {
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		try {
			$file = $folder->get($this->pfxFilename);
			$file->delete();
		} catch (NotPermittedException) {
			throw new LibresignException($this->l10n->t('You do not have permission for this action.'));
		} catch (NotFoundException|InvalidPathException) {
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
		try {
			/** @var \OCP\Files\File */
			$node = $folder->get($this->pfxFilename);
			$this->certificate = $node->getContent();
		} catch (GenericFileException|NotFoundException) {
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

	#[\Override]
	public function getLastSignedDate(): \DateTime {
		$stream = $this->getFileStream();

		$chain = $this->getCertificateChain($stream);
		if (empty($chain)) {
			throw new \UnexpectedValueException('Certificate chain is empty.');
		}

		$last = $chain[array_key_last($chain)];
		if (!is_array($last) || !isset($last['signingTime']) || !$last['signingTime'] instanceof \DateTime) {
			$this->logger->error('Invalid signingTime in certificate chain.', ['chain' => $chain]);
			throw new \UnexpectedValueException('Invalid signingTime in certificate chain.');
		}

		// Prevent accepting certificates with future signing dates (possible clock issues)
		$dateTime = new \DateTime('now', new \DateTimeZone('UTC'));
		if ($last['signingTime'] > $dateTime) {
			$this->logger->error('We found Marty McFly', [
				'last_signature' => json_encode($last['signingTime']),
				'current_date_time' => json_encode($dateTime),
			]);
			throw new \UnexpectedValueException('Invalid signingTime in certificate chain. We found Marty McFly');
		}

		return $last['signingTime'];
	}

	/**
	 * @return resource
	 */
	protected function getFileStream() {
		$signedFile = $this->getInputFile();
		$stream = $signedFile->fopen('rb');
		if ($stream === false) {
			throw new \RuntimeException('Unable to open the signed file for reading.');
		}
		return $stream;
	}

	private function getCertificateEngine(): IEngineHandler {
		return \OCP\Server::get(CertificateEngineFactory::class)
			->getEngine();
	}
}
