<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use DateTime;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\OrderCertificatesTrait;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Service\FolderService;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\ITempManager;
use phpseclib3\File\ASN1;
use TypeError;

class Pkcs12Handler extends SignEngineHandler {
	use OrderCertificatesTrait;
	private string $pfxFilename = 'signature.pfx';
	protected string $certificate = '';
	private array $signaturesFromPoppler = [];
	/**
	 * Used by method self::getHandler()
	 */
	private ?JSignPdfHandler $jSignPdfHandler = null;

	public function __construct(
		private FolderService $folderService,
		private IAppConfig $appConfig,
		protected CertificateEngineFactory $certificateEngineFactory,
		private IL10N $l10n,
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

	public function updatePassword(string $uid, string $currentPrivateKey, string $newPrivateKey): string {
		$pfx = $this->getPfxOfCurrentSigner($uid);
		$content = $this->certificateEngineFactory->getEngine()->updatePassword(
			$pfx,
			$currentPrivateKey,
			$newPrivateKey
		);
		return $this->savePfx($uid, $content);
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
			$fromFallback = $this->popplerUtilsPdfSignFallback($resource, $signerCounter);
			if ($fromFallback) {
				$certificates[$signerCounter] = $fromFallback;
			}
			if (!$signature) {
				$certificates[$signerCounter]['chain'][0]['signature_validation'] = $this->getReadableSigState('Digest Mismatch.');
				$signerCounter++;
				continue;
			}

			if (!isset($fromFallback['signingTime'])) {
				// Probably the best way to do this would be:
				// ASN1::asn1map($decoded[0], Maps\TheMapName::MAP);
				// But, what's the MAP to use?
				//
				// With maps also could be possible read all certificate data and
				// maybe discart openssl at  this pint
				try {
					$decoded = ASN1::decodeBER($signature);
					$certificates[$signerCounter]['signingTime'] = $decoded[0]['content'][1]['content'][0]['content'][4]['content'][0]['content'][3]['content'][1]['content'][1]['content'][0]['content'];
				} catch (\Throwable) {
				}
			}

			$pkcs7PemSignature = $this->der2pem($signature);
			if (openssl_pkcs7_read($pkcs7PemSignature, $pemCertificates)) {
				foreach ($pemCertificates as $certificateIndex => $pemCertificate) {
					$parsed = openssl_x509_parse($pemCertificate);
					foreach ($parsed as $key => $value) {
						if (!isset($certificates[$signerCounter]['chain'][$certificateIndex][$key])) {
							$certificates[$signerCounter]['chain'][$certificateIndex][$key] = $value;
						} elseif ($key === 'name') {
							$certificates[$signerCounter]['chain'][$certificateIndex][$key] = $value;
						} elseif ($key === 'signatureTypeSN' && $certificates[$signerCounter]['chain'][$certificateIndex][$key] !== $value) {
							$certificates[$signerCounter]['chain'][$certificateIndex][$key] = $value;
						}
					}
					if (empty($certificates[$signerCounter]['chain'][$certificateIndex]['signature_validation'])) {
						$certificates[$signerCounter]['chain'][$certificateIndex]['signature_validation'] = [
							'id' => 1,
							'label' => $this->l10n->t('Signature is valid.'),
						];
					}
				}
			};
			$certificates[$signerCounter]['chain'] = $this->orderCertificates($certificates[$signerCounter]['chain']);
			$signerCounter++;
		}
		return $certificates;
	}

	private function popplerUtilsPdfSignFallback($resource, int $signerCounter): array {
		if (shell_exec('which pdfsig') === null) {
			return [];
		}
		if (!empty($this->signaturesFromPoppler)) {
			return $this->signaturesFromPoppler[$signerCounter] ?? [];
		}
		rewind($resource);
		$content = stream_get_contents($resource);
		$tempFile = $this->tempManager->getTemporaryFile('file.pdf');
		file_put_contents($tempFile, $content);

		$content = shell_exec('pdfsig ' . $tempFile);
		if (empty($content)) {
			return [];
		}
		$lines = explode("\n", $content);

		$lastSignature = 0;
		foreach ($lines as $item) {
			$isFirstLevel = preg_match('/^Signature\s#(\d)/', $item, $match);
			if ($isFirstLevel) {
				$lastSignature = (int)$match[1] - 1;
				$this->signaturesFromPoppler[$lastSignature] = [];
				continue;
			}


			$match = [];
			$isSecondLevel = preg_match('/^\s+-\s(?<key>.+):\s(?<value>.*)/', $item, $match);
			if ($isSecondLevel) {
				switch ((string)$match['key']) {
					case 'Signing Time':
						$this->signaturesFromPoppler[$lastSignature]['signingTime'] = DateTime::createFromFormat('M d Y H:i:s', $match['value']);
						break;
					case 'Signer full Distinguished Name':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['subject'] = $this->parseDistinguishedNameWithMultipleValues($match['value']);
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['name'] = $match['value'];
						break;
					case 'Signing Hash Algorithm':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['signatureTypeSN'] = $match['value'];
						break;
					case 'Signature Validation':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['signature_validation'] = $this->getReadableSigState($match['value']);
						break;
					case 'Certificate Validation':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['certificate_validation'] = $this->getReadableCertState($match['value']);
						break;
					case 'Signed Ranges':
						preg_match('/\[(\d+) - (\d+)\], \[(\d+) - (\d+)\]/', $match['value'], $ranges);
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['range'] = [
							'offset1' => (int)$ranges[1],
							'length1' => (int)$ranges[2],
							'offset2' => (int)$ranges[3],
							'length2' => (int)$ranges[4],
						];
						break;
					case 'Signature Field Name':
						$this->signaturesFromPoppler[$lastSignature]['chain'][0]['field'] = $match['value'];
						break;
					case 'Signature Validation':
					case 'Signature Type':
					case 'Total document signed':
					case 'Not total document signed':
					default:
						break;
				}
			}
		}
		return $this->signaturesFromPoppler[$signerCounter] ?? [];
	}

	private function getReadableSigState(string $status) {
		return match ($status) {
			'Signature is Valid.' => [
				'id' => 1,
				'label' => $this->l10n->t('Signature is valid.'),
			],
			'Signature is Invalid.' => [
				'id' => 2,
				'label' => $this->l10n->t('Signature is invalid.'),
			],
			'Digest Mismatch.' => [
				'id' => 3,
				'label' => $this->l10n->t('Digest mismatch.'),
			],
			"Document isn't signed or corrupted data." => [
				'id' => 4,
				'label' => $this->l10n->t("Document isn't signed or corrupted data."),
			],
			'Signature has not yet been verified.' => [
				'id' => 5,
				'label' => $this->l10n->t('Signature has not yet been verified.'),
			],
			default => [
				'id' => 6,
				'label' => $this->l10n->t('Unknown validation failure.'),
			],
		};
	}


	private function getReadableCertState(string $status) {
		return match ($status) {
			'Certificate is Trusted.' => [
				'id' => 1,
				'label' => $this->l10n->t('Certificate is trusted.'),
			],
			"Certificate issuer isn't Trusted." => [
				'id' => 2,
				'label' => $this->l10n->t("Certificate issuer isn't trusted."),
			],
			'Certificate issuer is unknown.' => [
				'id' => 3,
				'label' => $this->l10n->t('Certificate issuer is unknown.'),
			],
			'Certificate has been Revoked.' => [
				'id' => 4,
				'label' => $this->l10n->t('Certificate has been revoked.'),
			],
			'Certificate has Expired' => [
				'id' => 5,
				'label' => $this->l10n->t('Certificate has expired'),
			],
			'Certificate has not yet been verified.' => [
				'id' => 6,
				'label' => $this->l10n->t('Certificate has not yet been verified.'),
			],
			default => [
				'id' => 7,
				'label' => $this->l10n->t('Unknown issue with Certificate or corrupted data.')
			],
		};
	}


	private function parseDistinguishedNameWithMultipleValues(string $dn): array {
		$result = [];
		$pairs = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $dn);

		foreach ($pairs as $pair) {
			[$key, $value] = explode('=', $pair, 2);
			$key = trim($key);
			$value = trim($value);
			$value = trim($value, '"');

			if (!isset($result[$key])) {
				$result[$key] = $value;
			} else {
				if (!is_array($result[$key])) {
					$result[$key] = [$result[$key]];
				}
				$result[$key][] = $value;
			}
		}

		return $result;
	}

	private function der2pem($derData) {
		$pem = chunk_split(base64_encode((string)$derData), 64, "\n");
		$pem = "-----BEGIN CERTIFICATE-----\n" . $pem . "-----END CERTIFICATE-----\n";
		return $pem;
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
		} catch (GenericFileException $e) {
			throw new LibresignException($this->l10n->t('Password to sign not defined. Create a password to sign.'), 400);
		} catch (\Throwable) {
		}
		if (empty($this->certificate)) {
			throw new LibresignException($this->l10n->t('Password to sign not defined. Create a password to sign.'), 400);
		}
		if ($this->getPassword()) {
			try {
				$this->certificateEngineFactory->getEngine()->readCertificate($this->certificate, $this->getPassword());
			} catch (InvalidPasswordException) {
				throw new LibresignException($this->l10n->t('Invalid password'));
			}
		}
		return $this->certificate;
	}

	private function getHandler(): SignEngineHandler {
		$sign_engine = $this->appConfig->getValueString(Application::APP_ID, 'sign_engine', 'JSignPdf');
		$property = lcfirst($sign_engine) . 'Handler';
		if (!property_exists($this, $property)) {
			throw new LibresignException($this->l10n->t('Invalid Sign engine.'), 400);
		}
		$classHandler = 'OCA\\Libresign\\Handler\\SignEngine\\' . ucfirst($property);
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
			->setSignatureParams($this->getSignatureParams())
			->setVisibleElements($this->getVisibleElements())
			->getSignedContent();
		$this->getInputFile()->putContent($signedContent);
		return $this->getInputFile();
	}

	public function isHandlerOk(): bool {
		return $this->certificateEngineFactory->getEngine()->isSetupOk();
	}

	/**
	 * Generate certificate
	 *
	 * @param array $user Example: ['host' => '', 'name' => '']
	 * @param string $signPassword Password of signature
	 * @param string $friendlyName Friendly name
	 */
	public function generateCertificate(array $user, string $signPassword, string $friendlyName): string {
		$content = $this->certificateEngineFactory->getEngine()
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
}
