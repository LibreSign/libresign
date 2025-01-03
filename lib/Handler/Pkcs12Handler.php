<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler;

use OC\SystemConfig;
use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Service\FolderService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\File;
use OCP\IL10N;
use OCP\ITempManager;
use TypeError;

class Pkcs12Handler extends SignEngineHandler {
	private string $pfxFilename = 'signature.pfx';
	private string $pfxContent = '';

	public function __construct(
		private FolderService $folderService,
		private IAppConfig $appConfig,
		private SystemConfig $systemConfig,
		private CertificateEngineHandler $certificateEngineHandler,
		private IL10N $l10n,
		private JSignPdfHandler $jSignPdfHandler,
		private FooterHandler $footerHandler,
		private ITempManager $tempManager,
	) {
	}

	public function savePfx(string $uid, string $content): string {
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		if ($folder->nodeExists($this->pfxFilename)) {
			$file = $folder->get($this->pfxFilename);
			if (!$file instanceof File) {
				throw new LibresignException("path {$this->pfxFilename} already exists and is not a file!", 400);
			}
			$file->putContent($content);
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
		} catch (\Throwable $th) {
		}
	}

	public function updatePassword(string $uid, string $currentPrivateKey, string $newPrivateKey): string {
		$pfx = $this->getPfx($uid);
		$content = $this->certificateEngineHandler->getEngine()->updatePassword(
			$pfx,
			$currentPrivateKey,
			$newPrivateKey
		);
		return $this->savePfx($uid, $content);
	}

	public function readCertificate(string $uid, string $privateKey): array {
		$this->setPassword($privateKey);
		$pfx = $this->getPfx($uid);
		return $this->certificateEngineHandler->getEngine()->readCertificate(
			$pfx,
			$privateKey
		);
	}

	/**
	 * @param resource $resource
	 * @return array
	 */
	public function validatePdfContent($resource): array {
		$content = stream_get_contents($resource);
		preg_match_all('/ByteRange\s*\[(\d+) (?<start>\d+) (?<end>\d+) (\d+)?/', $content, $bytes);
		if (empty($bytes['start']) || empty($bytes['end'])) {
			throw new LibresignException($this->l10n->t('Unsigned file.'));
		}

		$parsed = [];
		for ($i = 0; $i < count($bytes['start']); $i++) {
			rewind($resource);
			$signature = stream_get_contents(
				$resource,
				$bytes['end'][$i] - $bytes['start'][$i] - 2,
				$bytes['start'][$i] + 1
			);
			$pfxCertificate = hex2bin($signature);
			if (empty($tempFile)) {
				$tempFile = $this->tempManager->getTemporaryFile('cert.pfx');
			}
			file_put_contents($tempFile, $pfxCertificate);
			$output = shell_exec("openssl pkcs7 -in {$tempFile} -inform DER -print_certs");
			$parsed[] = openssl_x509_parse($output);
		}
		$this->tempManager->clean();
		return $parsed;
	}

	public function setPfxContent(string $content): void {
		$this->pfxContent = $content;
	}

	/**
	 * Get content of pfx file
	 */
	public function getPfx(?string $uid = null): string {
		if (!empty($this->pfxContent) || empty($uid)) {
			return $this->pfxContent;
		}
		$this->folderService->setUserId($uid);
		$folder = $this->folderService->getFolder();
		if (!$folder->nodeExists($this->pfxFilename)) {
			throw new LibresignException($this->l10n->t('Password to sign not defined. Create a password to sign.'), 400);
		}
		/** @var \OCP\Files\File */
		$node = $folder->get($this->pfxFilename);
		$this->pfxContent = $node->getContent();
		if (empty($this->pfxContent)) {
			throw new LibresignException($this->l10n->t('Password to sign not defined. Create a password to sign.'), 400);
		}
		if ($this->getPassword()) {
			try {
				$this->certificateEngineHandler->getEngine()->opensslPkcs12Read($this->pfxContent, $this->getPassword());
			} catch (InvalidPasswordException $e) {
				throw new LibresignException($this->l10n->t('Invalid password'));
			}
		}
		return $this->pfxContent;
	}

	private function getHandler(): SignEngineHandler {
		$sign_engine = $this->appConfig->getAppValue('sign_engine', 'JSignPdf');
		$property = lcfirst($sign_engine) . 'Handler';
		if (!property_exists($this, $property)) {
			throw new LibresignException($this->l10n->t('Invalid Sign engine.'), 400);
		}
		$classHandler = 'OCA\\Libresign\\Handler\\' . $property;
		if (!$this->$property instanceof $classHandler) {
			$this->$property = \OCP\Server::get($classHandler);
		}
		return $this->$property;
	}

	public function sign(): File {
		$signedContent = $this->getHandler()
			->setCertificate($this->getCertificate())
			->setInputFile($this->getInputFile())
			->setPassword($this->getPassword())
			->setVisibleElements($this->getvisibleElements())
			->sign();
		$this->getInputFile()->putContent($signedContent);
		return $this->getInputFile();
	}

	public function isHandlerOk(): bool {
		return $this->certificateEngineHandler->getEngine()->isSetupOk();
	}

	/**
	 * Generate certificate
	 *
	 * @param array $user Example: ['host' => '', 'name' => '']
	 * @param string $signPassword Password of signature
	 * @param string $friendlyName Friendly name
	 * @param bool $isTempFile
	 */
	public function generateCertificate(array $user, string $signPassword, string $friendlyName, bool $isTempFile = false): string {
		$content = $this->certificateEngineHandler->getEngine()
			->setHosts([$user['host']])
			->setCommonName($user['name'])
			->setFriendlyName($friendlyName)
			->setUID($user['uid'])
			->setPassword($signPassword)
			->generateCertificate();
		if (!$content) {
			throw new TypeError();
		}
		if ($isTempFile) {
			return $content;
		}
		return $content;
	}
}
