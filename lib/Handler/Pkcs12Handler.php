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
use phpseclib3\File\ASN1;
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
	 * @throws LibresignException When is not a signed file
	 */
	private function getSignatures($resource): iterable {
		$content = stream_get_contents($resource);
		preg_match_all(
			'/ByteRange\s*\[\s*(?<offset1>\d+)\s+(?<length1>\d+)\s+(?<offset2>\d+)\s+(?<length2>\d+)\s*\]/',
			$content,
			$bytes
		);
		if (empty($bytes['offset1']) || empty($bytes['length1']) || empty($bytes['offset2']) || empty($bytes['length2'])) {
			throw new LibresignException($this->l10n->t('Unsigned file.'));
		}

		for ($i = 0; $i < count($bytes['offset1']); $i++) {
			// Starting position (in bytes) of the first part of the PDF that will be included in the validation.
			$offset1 = (int)$bytes['offset1'][$i];
			// Length (in bytes) of the first part.
			$length1 = (int)$bytes['length1'][$i];
			// Starting position (in bytes) of the second part, immediately after the signature.
			$offset2 = (int)$bytes['offset2'][$i];

			$signatureStart = $offset1 + $length1 + 1;
			$signatureLength = $offset2 - $signatureStart - 1;

			rewind($resource);

			$signature = stream_get_contents($resource, $signatureLength, $signatureStart);

			yield hex2bin($signature);
		}

		$this->tempManager->clean();
	}

	/**
	 * @param resource $resource
	 * @throws LibresignException When is not a signed file
	 * @return array
	 */
	public function getCertificateChain($resource): array {
		$signerCounter = 0;
		$certificates = [];
		foreach ($this->getSignatures($resource) as $signature) {
			// The signature could be invalid
			if (!$signature) {
				continue;
			}

			// Probably the best way to do this would be:
			// ASN1::asn1map($decoded[0], Maps\TheMapName::MAP);
			// But, what's the MAP to use?
			//
			// With maps also could be possible read all certificate data and
			// maybe discart openssl at  this pint
			try {
				$decoded = ASN1::decodeBER($signature);
				$certificates[$signerCounter]['signingTime'] = $decoded[0]['content'][1]['content'][0]['content'][4]['content'][0]['content'][3]['content'][1]['content'][1]['content'][0]['content'];
			} catch (\Throwable $th) {
			}

			$pkcs7PemSignature = $this->der2pem($signature);
			if (openssl_pkcs7_read($pkcs7PemSignature, $pemCertificates)) {
				foreach ($pemCertificates as $pemCertificate) {
					$certificates[$signerCounter]['chain'][] = openssl_x509_parse($pemCertificate);
				}
			};
			$certificates[$signerCounter]['chain'] = $this->orderList($certificates[$signerCounter]['chain']);
			$signerCounter++;
		}
		return $certificates;
	}

	private function orderList(array $certificates): array {
		$ordered = [];
		$map = [];

		$tree = current($certificates);
		foreach ($certificates as $cert) {
			if ($tree['subject'] === $cert['issuer']) {
				$tree = $cert;
			}
			$map[$cert['name']] = $cert;
		}

		if (!$tree) {
			return $certificates;
		}
		unset($map[$tree['name']]);
		$ordered[] = $tree;

		$current = $tree;
		while (!empty($map)) {
			if ($current['subject'] === $tree['issuer']) {
				$ordered[] = $current;
				$tree = $current;
				unset($map[$current['name']]);
				$current = reset($map);
				continue;
			}
			$current = next($map);
		}

		return $ordered;
	}

	private function der2pem($derData) {
		$pem = chunk_split(base64_encode($derData), 64, "\n");
		$pem = "-----BEGIN CERTIFICATE-----\n" . $pem . "-----END CERTIFICATE-----\n";
		return $pem;
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
